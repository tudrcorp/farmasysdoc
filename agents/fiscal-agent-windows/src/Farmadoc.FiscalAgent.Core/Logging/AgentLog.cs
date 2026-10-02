using System;
using System.IO;
using System.Linq;

namespace Farmadoc.FiscalAgent.Logging
{
    public interface IAgentLog
    {
        void Info(string message);

        void Warn(string message);

        void Error(string message, Exception exception = null);
    }

    /// <summary>
    /// Log diario en %ProgramData%\FarmadocFiscalAgent\logs (se conservan 30 días). Opcionalmente replica en consola.
    /// </summary>
    public sealed class FileAgentLog : IAgentLog
    {
        private readonly string _directory;
        private readonly bool _echoToConsole;
        private readonly object _lock = new object();
        private DateTime _lastPurge = DateTime.MinValue;

        public FileAgentLog(string directory, bool echoToConsole)
        {
            _directory = directory;
            _echoToConsole = echoToConsole;
            Directory.CreateDirectory(_directory);
        }

        public void Info(string message) => Write("INFO", message);

        public void Warn(string message) => Write("WARN", message);

        public void Error(string message, Exception exception = null) =>
            Write("ERROR", exception == null ? message : message + " | " + exception.GetType().Name + ": " + exception.Message + Environment.NewLine + exception.StackTrace);

        private void Write(string level, string message)
        {
            var line = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss.fff") + " [" + level + "] " + message;

            lock (_lock)
            {
                try
                {
                    File.AppendAllText(Path.Combine(_directory, "agent-" + DateTime.Now.ToString("yyyyMMdd") + ".log"), line + Environment.NewLine);
                    PurgeOldFiles();
                }
                catch (IOException)
                {
                    // Un fallo de log nunca debe detener la impresión fiscal.
                }

                if (_echoToConsole)
                {
                    Console.WriteLine(line);
                }
            }
        }

        private void PurgeOldFiles()
        {
            if ((DateTime.Now - _lastPurge).TotalHours < 12)
            {
                return;
            }

            _lastPurge = DateTime.Now;
            foreach (var file in Directory.GetFiles(_directory, "agent-*.log").Where(f => File.GetLastWriteTime(f) < DateTime.Now.AddDays(-30)))
            {
                File.Delete(file);
            }
        }
    }
}
