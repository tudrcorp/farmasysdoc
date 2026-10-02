using System;
using System.ServiceProcess;
using System.Threading;
using System.Threading.Tasks;

namespace Farmadoc.FiscalAgent.Service
{
    public sealed class FiscalAgentService : ServiceBase
    {
        public const string Name = "FarmadocFiscalAgent";

        private readonly string _configPath;
        private CancellationTokenSource _cancellation;
        private AgentHost _host;
        private Task _run;

        public FiscalAgentService(string configPath)
        {
            _configPath = configPath;
            ServiceName = Name;
            CanStop = true;
            CanShutdown = true;
            AutoLog = true;
        }

        protected override void OnStart(string[] args)
        {
            _host = AgentHost.Create(_configPath, echoToConsole: false);
            _cancellation = new CancellationTokenSource();
            _run = Task.Run(() => _host.Runner.RunAsync(_cancellation.Token));
        }

        protected override void OnStop()
        {
            StopAgent();
        }

        protected override void OnShutdown()
        {
            StopAgent();
        }

        private void StopAgent()
        {
            if (_cancellation == null)
            {
                return;
            }

            RequestAdditionalTime(90000);
            _cancellation.Cancel();

            try
            {
                // Si hay un documento a mitad de impresión, se le da tiempo de terminar.
                _run?.Wait(TimeSpan.FromSeconds(75));
            }
            catch (AggregateException)
            {
            }

            _host?.Dispose();
            _cancellation.Dispose();
            _cancellation = null;
        }
    }
}
