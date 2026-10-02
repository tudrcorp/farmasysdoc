using System;
using System.Collections.Generic;
using System.Net;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Text;
using System.Threading;
using System.Threading.Tasks;
using Farmadoc.FiscalAgent.Models;

namespace Farmadoc.FiscalAgent.Server
{
    public interface IFiscalServer
    {
        /// <summary>Devuelve null si no hay trabajo (HTTP 204).</summary>
        Task<ClaimResponse> ClaimAsync(int waitSeconds, CancellationToken cancellationToken);

        Task MarkStartedAsync(string uuid, int counterBefore, CancellationToken cancellationToken);

        Task SendResultAsync(string uuid, JobResult result, CancellationToken cancellationToken);

        Task<HeartbeatResponse> SendHeartbeatAsync(HeartbeatData data, CancellationToken cancellationToken);
    }

    public sealed class HeartbeatData
    {
        public string AgentVersion { get; set; }

        public string LastFiscalNumber { get; set; }

        public string LastZNumber { get; set; }

        public Dictionary<string, object> Status { get; set; } = new Dictionary<string, object>();
    }

    /// <summary>El servidor rechazó la operación por el estado del documento (HTTP 409); reintentar no sirve.</summary>
    public sealed class ServerConflictException : Exception
    {
        public ServerConflictException(string message) : base(message)
        {
        }
    }

    /// <summary>Token inválido o máquina desactivada (HTTP 401).</summary>
    public sealed class ServerUnauthorizedException : Exception
    {
        public ServerUnauthorizedException(string message) : base(message)
        {
        }
    }

    public sealed class FiscalServerClient : IFiscalServer, IDisposable
    {
        private readonly HttpClient _http;

        public FiscalServerClient(AgentConfig config, string agentVersion)
        {
            ServicePointManager.SecurityProtocol |= SecurityProtocolType.Tls12;

            _http = new HttpClient
            {
                BaseAddress = new Uri(config.ServerUrl.TrimEnd('/') + "/api/fiscal-agent/v1/"),
                Timeout = TimeSpan.FromSeconds(config.HttpTimeoutSeconds),
            };
            _http.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", config.AgentToken);
            _http.DefaultRequestHeaders.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
            _http.DefaultRequestHeaders.UserAgent.ParseAdd("FarmadocFiscalAgent/" + agentVersion);
        }

        /// <summary>Capacidades que este agente declara al servidor (p. ej. procesar simulaciones sin imprimir).</summary>
        public static readonly string[] Capabilities = { "simulation", "test_lab" };

        public async Task<ClaimResponse> ClaimAsync(int waitSeconds, CancellationToken cancellationToken)
        {
            using (var response = await PostAsync("jobs/claim", new { wait = waitSeconds, capabilities = Capabilities }, cancellationToken).ConfigureAwait(false))
            {
                if (response.StatusCode == HttpStatusCode.NoContent)
                {
                    return null;
                }

                var body = await EnsureSuccessAsync(response).ConfigureAwait(false);
                return AgentJson.Deserialize<ClaimResponse>(body);
            }
        }

        public async Task MarkStartedAsync(string uuid, int counterBefore, CancellationToken cancellationToken)
        {
            using (var response = await PostAsync("jobs/" + uuid + "/started", new { counter_before = counterBefore.ToString() }, cancellationToken).ConfigureAwait(false))
            {
                await EnsureSuccessAsync(response).ConfigureAwait(false);
            }
        }

        public async Task SendResultAsync(string uuid, JobResult result, CancellationToken cancellationToken)
        {
            using (var response = await PostAsync("jobs/" + uuid + "/result", result, cancellationToken).ConfigureAwait(false))
            {
                await EnsureSuccessAsync(response).ConfigureAwait(false);
            }
        }

        public async Task<HeartbeatResponse> SendHeartbeatAsync(HeartbeatData data, CancellationToken cancellationToken)
        {
            using (var response = await PostAsync("heartbeat", data, cancellationToken).ConfigureAwait(false))
            {
                var body = await EnsureSuccessAsync(response).ConfigureAwait(false);
                return string.IsNullOrWhiteSpace(body) ? new HeartbeatResponse() : AgentJson.Deserialize<HeartbeatResponse>(body);
            }
        }

        public void Dispose()
        {
            _http.Dispose();
        }

        private Task<HttpResponseMessage> PostAsync(string path, object body, CancellationToken cancellationToken)
        {
            var content = new StringContent(AgentJson.Serialize(body), Encoding.UTF8, "application/json");
            return _http.PostAsync(path, content, cancellationToken);
        }

        private static async Task<string> EnsureSuccessAsync(HttpResponseMessage response)
        {
            var body = response.Content == null ? string.Empty : await response.Content.ReadAsStringAsync().ConfigureAwait(false);

            if (response.IsSuccessStatusCode)
            {
                return body;
            }

            switch (response.StatusCode)
            {
                case HttpStatusCode.Conflict:
                    throw new ServerConflictException(body);
                case HttpStatusCode.Unauthorized:
                    throw new ServerUnauthorizedException(body);
                default:
                    throw new HttpRequestException("HTTP " + (int)response.StatusCode + ": " + Truncate(body, 500));
            }
        }

        private static string Truncate(string value, int max)
        {
            return value == null || value.Length <= max ? value : value.Substring(0, max) + "…";
        }
    }
}
