using System;
using System.IO;
using System.Linq;
using System.ServiceProcess;
using System.Text;
using System.Threading;
using Farmadoc.FiscalAgent.Models;
using Farmadoc.FiscalAgent.Printing;
using Farmadoc.FiscalAgent.Runtime;
using Farmadoc.FiscalAgent.Server;
using Newtonsoft.Json.Linq;

namespace Farmadoc.FiscalAgent.Service
{
    /// <summary>
    /// FarmadocFiscalAgent.exe                     → servicio de Windows (lo usa sc.exe).
    /// FarmadocFiscalAgent.exe --console           → mismo agente en primer plano (Ctrl+C para salir).
    /// FarmadocFiscalAgent.exe --check             → diagnóstico de solo lectura: máquina fiscal + servidor.
    /// FarmadocFiscalAgent.exe --preview doc.json  → muestra los comandos que se enviarían, sin imprimir.
    /// Opcional en todos: --config C:\ruta\agent.json
    /// </summary>
    public static class Program
    {
        public static int Main(string[] args)
        {
            Console.OutputEncoding = Encoding.UTF8;

            var configPath = OptionValue(args, "--config") ?? AgentHost.DefaultConfigPath;

            try
            {
                if (args.Contains("--version"))
                {
                    Console.WriteLine(AgentHost.Version);
                    return 0;
                }

                if (args.Contains("--check"))
                {
                    return Check(configPath);
                }

                var previewFile = OptionValue(args, "--preview");
                if (previewFile != null)
                {
                    return Preview(configPath, previewFile);
                }

                if (args.Contains("--console") || Environment.UserInteractive)
                {
                    return RunConsole(configPath);
                }

                ServiceBase.Run(new FiscalAgentService(configPath));
                return 0;
            }
            catch (AgentConfigException ex)
            {
                Console.Error.WriteLine(ex.Message);
                return 2;
            }
        }

        private static int RunConsole(string configPath)
        {
            using (var host = AgentHost.Create(configPath, echoToConsole: true))
            using (var cancellation = new CancellationTokenSource())
            {
                Console.CancelKeyPress += (sender, e) =>
                {
                    e.Cancel = true;
                    cancellation.Cancel();
                };

                Console.WriteLine("Agente en consola. Ctrl+C para detener.");
                host.Runner.RunAsync(cancellation.Token).GetAwaiter().GetResult();
            }

            return 0;
        }

        private static int Check(string configPath)
        {
            var ok = true;

            using (var host = AgentHost.Create(configPath, echoToConsole: false))
            {
                var config = host.Config;
                Console.WriteLine("Configuración: " + Path.GetFullPath(configPath));
                Console.WriteLine("Servidor:      " + config.ServerUrl);
                Console.WriteLine("Puerto:        " + config.ComPort);
                Console.WriteLine();

                try
                {
                    using (var session = host.Printer.Open())
                    {
                        var snapshot = session.ReadSnapshot();
                        var taxes = session.ReadTaxes();
                        host.Cache.Save(snapshot, taxes);

                        Console.WriteLine("Máquina fiscal");
                        Console.WriteLine("  Estado:            " + snapshot.StatusDescription + " (error: " + snapshot.ErrorDescription + ")");
                        Console.WriteLine("  Registro:          " + snapshot.RegisteredMachineNumber + "   RIF: " + snapshot.Rif);
                        Console.WriteLine("  Última factura:    " + snapshot.LastInvoiceNumber);
                        Console.WriteLine("  Última N/C:        " + snapshot.LastCreditNoteNumber);
                        Console.WriteLine("  Contador de Z:     " + snapshot.DailyClosureCounter);
                        Console.WriteLine("  Alícuotas:         " + taxes.Tax1 + "% / " + taxes.Tax2 + "% / " + taxes.Tax3 + "%");
                        Console.WriteLine("  IGTF programado:   " + (taxes.ComputesIgtf ? taxes.IgtfRate + "%" : "NO"));

                        if (snapshot.PrinterDateTime.HasValue)
                        {
                            var drift = (snapshot.PrinterDateTime.Value - DateTime.Now).TotalMinutes;
                            Console.WriteLine("  Reloj máquina:     " + snapshot.PrinterDateTime.Value.ToString("dd/MM/yyyy HH:mm:ss")
                                + (Math.Abs(drift) > 5 ? "   ⚠ difiere " + Math.Round(drift) + " min de la PC" : "   (OK)"));
                        }

                        var expected = new string((host.Settings.ExpectedRegistry ?? string.Empty).ToUpperInvariant().Where(char.IsLetterOrDigit).ToArray());
                        var actual = new string((snapshot.RegisteredMachineNumber ?? string.Empty).ToUpperInvariant().Where(char.IsLetterOrDigit).ToArray());
                        if (expected.Length == 0)
                        {
                            Console.WriteLine("  ⚠ Aún no se conoce el registro esperado (se recibe de Farmaadmin al conectar).");
                        }
                        else if (actual.Length == 0 || !(expected.StartsWith(actual) || actual.StartsWith(expected)))
                        {
                            ok = false;
                            Console.WriteLine("  ✗ El registro no coincide con el configurado (" + host.Settings.ExpectedRegistry + ").");
                        }

                        if (taxes.SystemFlags.Length > 21 && taxes.SystemFlags[21] != 0)
                        {
                            Console.WriteLine("  ⚠ Flag 21 = " + taxes.SystemFlags[21] + ": la máquina usa un formato de montos extendido; revise command_format en agent.json.");
                        }
                    }
                }
                catch (Exception ex)
                {
                    ok = false;
                    Console.WriteLine("  ✗ " + ex.Message);
                }

                Console.WriteLine();
                try
                {
                    var response = host.Server.SendHeartbeatAsync(new HeartbeatData
                    {
                        AgentVersion = AgentHost.Version,
                        Status = host.Cache.ToStatusDictionary(),
                    }, CancellationToken.None).GetAwaiter().GetResult();
                    host.Settings.Update(response?.Config);
                    Console.WriteLine("Servidor: ✓ conectado y token válido. Modo de esta máquina: " + host.Settings.Mode + ".");
                }
                catch (Exception ex)
                {
                    ok = false;
                    Console.WriteLine("Servidor: ✗ " + ex.Message);
                }

                Console.WriteLine();
                Console.WriteLine("Medios de pago (configurados en Farmaadmin para esta máquina):");
                var slots = host.Settings.PaymentSlots;
                foreach (var code in new[] { "cash_ves", "cash_usd", "card_ves", "mobile_payment_ves", "transfer_ves", "transfer_usd", "zelle", "cashea", "other_ves" })
                {
                    Console.WriteLine("  " + code.PadRight(20) + (slots.TryGetValue(code, out var slot) ? slot : "✗ sin asignar"));
                }
            }

            Console.WriteLine();
            Console.WriteLine(ok ? "Diagnóstico OK." : "Diagnóstico con errores.");
            return ok ? 0 : 1;
        }

        private static int Preview(string configPath, string file)
        {
            var config = AgentConfig.Load(configPath);
            var json = JObject.Parse(File.ReadAllText(file));
            var payloadJson = json["job"]?["payload"] as JObject ?? json["payload"] as JObject ?? json;
            var payload = new AgentJob { Payload = payloadJson }.ReadPayload();

            PrinterTaxes taxes;
            try
            {
                using (var session = new HkaFiscalPrinter(config.ComPort, config.BaudRate, false).Open())
                {
                    taxes = session.ReadTaxes();
                }
            }
            catch (Exception ex)
            {
                Console.WriteLine("(Sin máquina fiscal: " + ex.Message + " — se asumen alícuotas 16/8/31.)");
                taxes = new PrinterTaxes { Tax1 = 16m, Tax2 = 8m, Tax3 = 31m };
            }

            var plan = new AgentSettings(config, Path.GetDirectoryName(Path.GetFullPath(configPath))).CreateBuilder().Build(payload, taxes);

            Console.WriteLine("Comandos (entre corchetes para ver espacios):");
            foreach (var command in plan.All)
            {
                Console.WriteLine("  [" + command + "]");
            }

            return 0;
        }

        private static string OptionValue(string[] args, string name)
        {
            var index = Array.IndexOf(args, name);
            return index >= 0 && index + 1 < args.Length ? args[index + 1] : null;
        }
    }
}
