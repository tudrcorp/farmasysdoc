using Newtonsoft.Json;
using Newtonsoft.Json.Serialization;

namespace Farmadoc.FiscalAgent
{
    /// <summary>
    /// JSON en snake_case, igual que la API de Farmadoc.
    /// </summary>
    public static class AgentJson
    {
        public static readonly JsonSerializerSettings Settings = new JsonSerializerSettings
        {
            ContractResolver = new DefaultContractResolver
            {
                NamingStrategy = new SnakeCaseNamingStrategy
                {
                    ProcessDictionaryKeys = false,
                    OverrideSpecifiedNames = false,
                },
            },
            NullValueHandling = NullValueHandling.Include,
            FloatParseHandling = FloatParseHandling.Decimal,
            DateParseHandling = DateParseHandling.None,
        };

        public static string Serialize(object value, bool indented = false)
        {
            return JsonConvert.SerializeObject(value, indented ? Formatting.Indented : Formatting.None, Settings);
        }

        public static T Deserialize<T>(string json)
        {
            return JsonConvert.DeserializeObject<T>(json, Settings);
        }
    }
}
