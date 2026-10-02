using System;
using System.IO;
using System.Reflection;
using Farmadoc.FiscalAgent.Logging;
using Farmadoc.FiscalAgent.Processing;
using Farmadoc.FiscalAgent.Runtime;
using Farmadoc.FiscalAgent.Server;

namespace Farmadoc.FiscalAgent.Service
{
    /// <summary>
    /// Arma las piezas del agente a partir de agent.json.
    /// </summary>
    public sealed class AgentHost : IDisposable
    {
        private AgentHost(AgentSettings settings, PrinterCache cache, IAgentLog log, FiscalServerClient server, HkaFiscalPrinter printer, LocalJournal journal, AgentRunner runner)
        {
            Settings = settings;
            Cache = cache;
            Log = log;
            Server = server;
            Printer = printer;
            Journal = journal;
            Runner = runner;
        }

        public static string Version => Assembly.GetExecutingAssembly().GetName().Version.ToString(3);

        public static string DefaultConfigPath => Path.Combine(AgentConfig.DefaultDataDirectory, "agent.json");

        public AgentConfig Config => Settings.Local;

        public AgentSettings Settings { get; }

        public PrinterCache Cache { get; }

        public IAgentLog Log { get; }

        public FiscalServerClient Server { get; }

        public HkaFiscalPrinter Printer { get; }

        public LocalJournal Journal { get; }

        public AgentRunner Runner { get; }

        public static AgentHost Create(string configPath, bool echoToConsole)
        {
            var config = AgentConfig.Load(configPath);
            var dataDirectory = Path.GetDirectoryName(Path.GetFullPath(configPath)) ?? AgentConfig.DefaultDataDirectory;
            var log = new FileAgentLog(Path.Combine(dataDirectory, "logs"), echoToConsole);
            var journal = new LocalJournal(Path.Combine(dataDirectory, "journal"));
            var server = new FiscalServerClient(config, Version);
            var printer = new HkaFiscalPrinter(config.ComPort, config.BaudRate, config.VerboseSdkLog);
            var settings = new AgentSettings(config, dataDirectory);
            var cache = new PrinterCache(dataDirectory);
            var processor = new JobProcessor(printer, server, journal, settings, cache, log);
            var runner = new AgentRunner(settings, cache, server, printer, processor, journal, log, Version);

            return new AgentHost(settings, cache, log, server, printer, journal, runner);
        }

        public void Dispose()
        {
            Server.Dispose();
        }
    }
}
