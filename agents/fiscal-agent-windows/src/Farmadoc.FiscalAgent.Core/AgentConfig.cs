using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using Farmadoc.FiscalAgent.Printing;
using Newtonsoft.Json;

namespace Farmadoc.FiscalAgent
{
    /// <summary>
    /// Configuración del agente (archivo agent.json en %ProgramData%\FarmadocFiscalAgent).
    /// </summary>
    public sealed class AgentConfig
    {
        public string ServerUrl { get; set; }

        public string AgentToken { get; set; }

        public string ComPort { get; set; } = "COM5";

        public int? BaudRate { get; set; }

        /// <summary>
        /// Nº de registro SENIAT esperado; el agente se niega a imprimir si la máquina conectada es otra.
        /// Opcional: si Farmaadmin lo envía, prevalece el del servidor.
        /// </summary>
        public string ExpectedRegistry { get; set; }

        public int ClaimWaitSeconds { get; set; } = 15;

        public int HeartbeatSeconds { get; set; } = 30;

        public int HttpTimeoutSeconds { get; set; } = 30;

        /// <summary>
        /// Código lógico de pago (cash_usd, card_ves…) → número de medio de pago programado en la máquina (01–24).
        /// Respaldo local: lo configurado en Farmaadmin para esta máquina prevalece.
        /// </summary>
        public Dictionary<string, string> PaymentSlots { get; set; } = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);

        public HkaCommandFormat CommandFormat { get; set; } = new HkaCommandFormat();

        public bool SendCustomerAddress { get; set; } = true;

        public bool VerboseSdkLog { get; set; }

        public static string DefaultDataDirectory =>
            Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), "FarmadocFiscalAgent");

        public static AgentConfig Load(string path)
        {
            if (!File.Exists(path))
            {
                throw new AgentConfigException("No existe el archivo de configuración: " + path);
            }

            var config = JsonConvert.DeserializeObject<AgentConfig>(File.ReadAllText(path), AgentJson.Settings)
                ?? throw new AgentConfigException("El archivo de configuración está vacío: " + path);

            config.PaymentSlots = new Dictionary<string, string>(config.PaymentSlots ?? new Dictionary<string, string>(), StringComparer.OrdinalIgnoreCase);
            config.CommandFormat = config.CommandFormat ?? new HkaCommandFormat();
            config.Validate();

            return config;
        }

        public void Validate()
        {
            var errors = new List<string>();

            if (string.IsNullOrWhiteSpace(ServerUrl) || !Uri.TryCreate(ServerUrl, UriKind.Absolute, out var uri)
                || (uri.Scheme != Uri.UriSchemeHttps && uri.Scheme != Uri.UriSchemeHttp))
            {
                errors.Add("server_url debe ser una URL absoluta (https://…).");
            }

            if (string.IsNullOrWhiteSpace(AgentToken) || !AgentToken.StartsWith("fd_fp_", StringComparison.Ordinal))
            {
                errors.Add("agent_token debe ser el token de la máquina fiscal (empieza por fd_fp_).");
            }

            if (string.IsNullOrWhiteSpace(ComPort))
            {
                errors.Add("com_port es obligatorio (p. ej. COM5).");
            }

            foreach (var slot in PaymentSlots.Where(p => !IsValidSlot(p.Value)))
            {
                errors.Add("payment_slots." + slot.Key + " debe ser un número de medio de pago de 01 a 24.");
            }

            ClaimWaitSeconds = Math.Max(0, Math.Min(ClaimWaitSeconds, 20));
            HeartbeatSeconds = Math.Max(10, HeartbeatSeconds);
            HttpTimeoutSeconds = Math.Max(ClaimWaitSeconds + 10, HttpTimeoutSeconds);

            if (errors.Count > 0)
            {
                throw new AgentConfigException("Configuración inválida:\n- " + string.Join("\n- ", errors));
            }
        }

        private static bool IsValidSlot(string value)
        {
            return value != null && value.Length == 2 && int.TryParse(value, out var n) && n >= 1 && n <= 24;
        }
    }

    public sealed class AgentConfigException : Exception
    {
        public AgentConfigException(string message) : base(message)
        {
        }
    }
}
