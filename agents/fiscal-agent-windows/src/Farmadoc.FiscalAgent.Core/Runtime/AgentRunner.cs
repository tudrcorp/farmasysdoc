using System;
using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;
using Farmadoc.FiscalAgent.Logging;
using Farmadoc.FiscalAgent.Models;
using Farmadoc.FiscalAgent.Printing;
using Farmadoc.FiscalAgent.Processing;
using Farmadoc.FiscalAgent.Server;

namespace Farmadoc.FiscalAgent.Runtime
{
    /// <summary>
    /// Bucle del agente: entrega resultados pendientes → pide trabajo (long-poll) → imprime → entrega.
    /// En paralelo envía un heartbeat con el estado de la máquina. El acceso al puerto es exclusivo.
    /// </summary>
    public sealed class AgentRunner
    {
        private static readonly TimeSpan MaxBackoff = TimeSpan.FromSeconds(60);

        private static readonly TimeSpan MinIdleClaimInterval = TimeSpan.FromSeconds(2);

        private readonly AgentConfig _config;
        private readonly AgentSettings _settings;
        private readonly PrinterCache _cache;
        private readonly IFiscalServer _server;
        private readonly IFiscalPrinter _printer;
        private readonly JobProcessor _processor;
        private readonly LocalJournal _journal;
        private readonly IAgentLog _log;
        private readonly string _agentVersion;
        private readonly SemaphoreSlim _printerGate = new SemaphoreSlim(1, 1);

        private Dictionary<string, object> _livePort = new Dictionary<string, object>();
        private string _lastFiscalNumber;
        private string _lastZNumber;

        public AgentRunner(AgentSettings settings, PrinterCache cache, IFiscalServer server, IFiscalPrinter printer, JobProcessor processor, LocalJournal journal, IAgentLog log, string agentVersion)
        {
            _settings = settings;
            _config = settings.Local;
            _cache = cache;
            _server = server;
            _printer = printer;
            _processor = processor;
            _journal = journal;
            _log = log;
            _agentVersion = agentVersion;
        }

        public async Task RunAsync(CancellationToken cancellationToken)
        {
            _log.Info("Agente fiscal " + _agentVersion + " iniciado · " + _config.ComPort + " · " + _config.ServerUrl);

            var heartbeat = Task.Run(() => HeartbeatLoopAsync(cancellationToken), cancellationToken);
            var backoff = TimeSpan.Zero;

            while (!cancellationToken.IsCancellationRequested)
            {
                try
                {
                    await DeliverPendingResultsAsync(cancellationToken).ConfigureAwait(false);

                    var claimStarted = DateTime.UtcNow;
                    var claim = await _server.ClaimAsync(_config.ClaimWaitSeconds, cancellationToken).ConfigureAwait(false);
                    backoff = TimeSpan.Zero;

                    var job = claim?.Job;
                    if (job == null)
                    {
                        // Si el servidor no retiene la petición (long-poll desactivado), no martillarlo.
                        var elapsed = DateTime.UtcNow - claimStarted;
                        if (elapsed < MinIdleClaimInterval)
                        {
                            await DelayAsync(MinIdleClaimInterval - elapsed, cancellationToken).ConfigureAwait(false);
                        }

                        continue;
                    }

                    ApplyRemoteConfig(claim.Config);

                    _log.Info("Trabajo recibido " + job.Uuid + " · " + job.Type + (job.Recovery ? " · recuperación" : string.Empty));

                    await _printerGate.WaitAsync(cancellationToken).ConfigureAwait(false);
                    try
                    {
                        var result = await _processor.ProcessAsync(job, cancellationToken).ConfigureAwait(false);
                        Remember(job.Type, result);
                    }
                    finally
                    {
                        _printerGate.Release();
                    }

                    await DeliverPendingResultsAsync(cancellationToken).ConfigureAwait(false);
                }
                catch (OperationCanceledException) when (cancellationToken.IsCancellationRequested)
                {
                    break;
                }
                catch (ServerUnauthorizedException ex)
                {
                    _log.Error("Token rechazado por el servidor (¿máquina desactivada o token regenerado?): " + ex.Message);
                    await DelayAsync(MaxBackoff, cancellationToken).ConfigureAwait(false);
                }
                catch (Exception ex)
                {
                    backoff = backoff == TimeSpan.Zero ? TimeSpan.FromSeconds(5) : TimeSpan.FromTicks(Math.Min(backoff.Ticks * 2, MaxBackoff.Ticks));
                    _log.Error("Error en el ciclo del agente; reintento en " + backoff.TotalSeconds + " s", ex);
                    await DelayAsync(backoff, cancellationToken).ConfigureAwait(false);
                }
            }

            try
            {
                await heartbeat.ConfigureAwait(false);
            }
            catch (OperationCanceledException)
            {
            }

            _log.Info("Agente fiscal detenido.");
        }

        private async Task DeliverPendingResultsAsync(CancellationToken cancellationToken)
        {
            foreach (var entry in _journal.PendingDeliveries())
            {
                try
                {
                    await _server.SendResultAsync(entry.Uuid, entry.Result, cancellationToken).ConfigureAwait(false);
                    _journal.MarkDelivered(entry.Uuid);
                }
                catch (ServerConflictException ex)
                {
                    _log.Warn("El servidor rechazó el resultado de " + entry.Uuid + " por conflicto de estado: " + ex.Message);
                    _journal.MarkDelivered(entry.Uuid, "conflict: " + ex.Message);
                }
            }

            _journal.PurgeDeliveredOlderThan(TimeSpan.FromDays(60));
        }

        private async Task HeartbeatLoopAsync(CancellationToken cancellationToken)
        {
            while (!cancellationToken.IsCancellationRequested)
            {
                try
                {
                    await SendHeartbeatAsync(cancellationToken).ConfigureAwait(false);
                }
                catch (OperationCanceledException) when (cancellationToken.IsCancellationRequested)
                {
                    return;
                }
                catch (Exception ex)
                {
                    _log.Warn("Heartbeat no enviado: " + ex.Message);
                }

                await DelayAsync(TimeSpan.FromSeconds(_config.HeartbeatSeconds), cancellationToken).ConfigureAwait(false);
            }
        }

        private async Task SendHeartbeatAsync(CancellationToken cancellationToken)
        {
            // Solo en modo Activa se consulta la máquina: en los otros modos el puerto pertenece al sistema
            // actual de la caja y abrirlo podría hacer fallar una venta suya.
            if (_settings.Mode == PrinterModes.Active)
            {
                if (await _printerGate.WaitAsync(0, cancellationToken).ConfigureAwait(false))
                {
                    try
                    {
                        _livePort = ReadPrinterStatus();
                    }
                    finally
                    {
                        _printerGate.Release();
                    }
                }
            }
            else
            {
                _livePort = new Dictionary<string, object> { ["port_available"] = null, ["port_note"] = "No se consulta el puerto en modo " + _settings.Mode + "." };
            }

            var status = _cache.ToStatusDictionary();
            foreach (var pair in _livePort)
            {
                status[pair.Key] = pair.Value;
            }

            status["com_port"] = _config.ComPort;
            status["agent_mode"] = _settings.Mode;
            status["pending_results"] = _journal.PendingDeliveries().Count;
            status["agent_time"] = DateTime.Now.ToString("yyyy-MM-ddTHH:mm:ss");

            var response = await _server.SendHeartbeatAsync(new HeartbeatData
            {
                AgentVersion = _agentVersion,
                LastFiscalNumber = _lastFiscalNumber,
                LastZNumber = _lastZNumber,
                Status = status,
            }, cancellationToken).ConfigureAwait(false);

            ApplyRemoteConfig(response?.Config);
        }

        private void ApplyRemoteConfig(RemoteAgentConfig config)
        {
            if (_settings.Update(config))
            {
                _log.Info("Modo de la máquina fiscal: " + _settings.Mode + ".");
            }
        }

        private Dictionary<string, object> ReadPrinterStatus()
        {
            try
            {
                using (var session = _printer.Open())
                {
                    var snapshot = session.ReadSnapshot();
                    var taxes = session.ReadTaxes();
                    _cache.Save(snapshot, taxes);

                    _lastFiscalNumber = snapshot.LastInvoiceNumber.ToString("D8");
                    _lastZNumber = snapshot.DailyClosureCounter.ToString();

                    return new Dictionary<string, object> { ["port_available"] = true };
                }
            }
            catch (Exception ex)
            {
                return new Dictionary<string, object>
                {
                    ["port_available"] = false,
                    ["error"] = ex.Message,
                };
            }
        }

        private void Remember(string type, JobResult result)
        {
            if (result.Outcome != JobOutcomes.Printed)
            {
                return;
            }

            if (type == FiscalDocumentTypes.Invoice && result.FiscalNumber != null)
            {
                _lastFiscalNumber = result.FiscalNumber;
            }

            if (type == FiscalDocumentTypes.ZReport && result.ZNumber != null)
            {
                _lastZNumber = result.ZNumber;
            }
        }

        private static async Task DelayAsync(TimeSpan delay, CancellationToken cancellationToken)
        {
            try
            {
                await Task.Delay(delay, cancellationToken).ConfigureAwait(false);
            }
            catch (OperationCanceledException)
            {
            }
        }
    }
}
