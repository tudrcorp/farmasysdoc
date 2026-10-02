using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using Farmadoc.FiscalAgent.Models;
using Farmadoc.FiscalAgent.Printing;

namespace Farmadoc.FiscalAgent.Runtime
{
    /// <summary>
    /// Configuración efectiva: lo que llega de Farmaadmin (por máquina) tiene prioridad sobre agent.json.
    /// La última configuración remota se guarda en disco para operar tras un reinicio sin internet.
    /// </summary>
    public sealed class AgentSettings
    {
        private readonly AgentConfig _local;
        private readonly string _path;
        private readonly object _lock = new object();
        private RemoteAgentConfig _remote;

        public AgentSettings(AgentConfig local, string dataDirectory)
        {
            _local = local;
            _path = dataDirectory == null ? null : Path.Combine(dataDirectory, "remote-config.json");
            _remote = LoadFromDisk();
        }

        public AgentConfig Local => _local;

        public string Mode
        {
            get
            {
                lock (_lock)
                {
                    return _remote?.Mode ?? PrinterModes.Disabled;
                }
            }
        }

        public string ExpectedRegistry
        {
            get
            {
                lock (_lock)
                {
                    return !string.IsNullOrWhiteSpace(_remote?.FiscalRegistry) ? _remote.FiscalRegistry : _local.ExpectedRegistry;
                }
            }
        }

        public IReadOnlyDictionary<string, string> PaymentSlots
        {
            get
            {
                lock (_lock)
                {
                    var remote = _remote?.PaymentSlots;
                    var source = remote != null && remote.Count > 0 ? remote : _local.PaymentSlots;

                    return new Dictionary<string, string>(source ?? new Dictionary<string, string>(), StringComparer.OrdinalIgnoreCase);
                }
            }
        }

        public HkaCommandFormat CommandFormat
        {
            get
            {
                lock (_lock)
                {
                    return _remote?.CommandFormat ?? _local.CommandFormat ?? new HkaCommandFormat();
                }
            }
        }

        public HkaCommandBuilder CreateBuilder()
        {
            return new HkaCommandBuilder(CommandFormat, PaymentSlots, _local.SendCustomerAddress);
        }

        /// <summary>Actualiza con lo recibido del servidor. Devuelve true si cambió el modo.</summary>
        public bool Update(RemoteAgentConfig remote)
        {
            if (remote == null)
            {
                return false;
            }

            bool modeChanged;
            lock (_lock)
            {
                modeChanged = _remote?.Mode != remote.Mode;
                _remote = remote;

                if (_path != null)
                {
                    try
                    {
                        File.WriteAllText(_path, AgentJson.Serialize(remote, indented: true));
                    }
                    catch (IOException)
                    {
                    }
                }
            }

            return modeChanged;
        }

        private RemoteAgentConfig LoadFromDisk()
        {
            try
            {
                return _path != null && File.Exists(_path) ? AgentJson.Deserialize<RemoteAgentConfig>(File.ReadAllText(_path)) : null;
            }
            catch (Exception)
            {
                return null;
            }
        }
    }

    /// <summary>
    /// Última lectura correcta de la máquina fiscal. En modo Simulación el puerto lo usa el sistema actual,
    /// así que el agente trabaja con estos datos y los reporta en el heartbeat.
    /// </summary>
    public sealed class PrinterCache
    {
        private readonly string _path;
        private readonly object _lock = new object();
        private PrinterCacheEntry _entry;

        public PrinterCache(string dataDirectory)
        {
            _path = dataDirectory == null ? null : Path.Combine(dataDirectory, "printer-cache.json");

            try
            {
                _entry = _path != null && File.Exists(_path) ? AgentJson.Deserialize<PrinterCacheEntry>(File.ReadAllText(_path)) : null;
            }
            catch (Exception)
            {
                _entry = null;
            }
        }

        public PrinterCacheEntry Current
        {
            get
            {
                lock (_lock)
                {
                    return _entry;
                }
            }
        }

        public void Save(PrinterSnapshot snapshot, PrinterTaxes taxes)
        {
            lock (_lock)
            {
                var entry = _entry ?? new PrinterCacheEntry();

                if (snapshot != null)
                {
                    entry.Status = snapshot.ToStatusDictionary();
                    entry.ClockDriftSeconds = snapshot.PrinterDateTime.HasValue
                        ? (int)(snapshot.PrinterDateTime.Value - DateTime.Now).TotalSeconds
                        : (int?)null;
                }

                if (taxes != null)
                {
                    entry.Taxes = taxes;
                }

                entry.ReadAt = DateTime.Now;
                _entry = entry;

                if (_path != null)
                {
                    try
                    {
                        File.WriteAllText(_path, AgentJson.Serialize(entry, indented: true));
                    }
                    catch (IOException)
                    {
                    }
                }
            }
        }

        /// <summary>Datos de la última lectura, en el formato que espera Farmaadmin.</summary>
        public Dictionary<string, object> ToStatusDictionary()
        {
            var entry = Current;
            var status = entry?.Status != null ? new Dictionary<string, object>(entry.Status) : new Dictionary<string, object>();

            if (entry?.Taxes != null)
            {
                status["tax_rates"] = new[] { entry.Taxes.Tax1, entry.Taxes.Tax2, entry.Taxes.Tax3 };
                status["igtf_rate"] = entry.Taxes.IgtfRate;
            }

            if (entry?.ClockDriftSeconds != null)
            {
                status["clock_drift_seconds"] = entry.ClockDriftSeconds.Value;
            }

            status["printer_read_at"] = entry?.ReadAt.ToString("yyyy-MM-ddTHH:mm:ss");

            return status;
        }

        public PrinterTaxes TaxesOrDefault(out string source)
        {
            var taxes = Current?.Taxes;
            if (taxes != null && new[] { taxes.Tax1, taxes.Tax2, taxes.Tax3 }.Any(t => t > 0m))
            {
                source = "printer_cache";
                return taxes;
            }

            source = "default_16_8_31";
            return new PrinterTaxes { Tax1 = 16m, Tax2 = 8m, Tax3 = 31m };
        }
    }

    public sealed class PrinterCacheEntry
    {
        public Dictionary<string, object> Status { get; set; }

        public PrinterTaxes Taxes { get; set; }

        public int? ClockDriftSeconds { get; set; }

        public DateTime ReadAt { get; set; }
    }
}
