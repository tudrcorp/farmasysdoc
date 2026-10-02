using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using Farmadoc.FiscalAgent.Models;

namespace Farmadoc.FiscalAgent.Processing
{
    /// <summary>
    /// Bitácora en disco por documento. Sobrevive a cortes de luz y caídas de internet:
    /// guarda el contador fiscal antes de imprimir y el resultado hasta que el servidor lo confirme.
    /// </summary>
    public sealed class LocalJournal
    {
        private readonly string _directory;
        private readonly object _lock = new object();

        public LocalJournal(string directory)
        {
            _directory = directory;
            Directory.CreateDirectory(_directory);
        }

        public JournalEntry Get(string uuid)
        {
            lock (_lock)
            {
                var path = PathFor(uuid);
                return File.Exists(path) ? AgentJson.Deserialize<JournalEntry>(File.ReadAllText(path)) : null;
            }
        }

        public JournalEntry SaveStarted(string uuid, string type, int counterBefore)
        {
            var entry = Get(uuid) ?? new JournalEntry { Uuid = uuid, Type = type, CreatedAt = DateTime.Now };
            entry.CounterBefore = counterBefore;
            entry.StartedAt = DateTime.Now;
            Write(entry);

            return entry;
        }

        public JournalEntry SaveResult(string uuid, string type, JobResult result)
        {
            var entry = Get(uuid) ?? new JournalEntry { Uuid = uuid, Type = type, CreatedAt = DateTime.Now };
            entry.Result = result;
            entry.CompletedAt = DateTime.Now;
            entry.Delivered = false;
            Write(entry);

            return entry;
        }

        public void MarkDelivered(string uuid, string note = null)
        {
            var entry = Get(uuid);
            if (entry == null)
            {
                return;
            }

            entry.Delivered = true;
            entry.DeliveredAt = DateTime.Now;
            entry.DeliveryNote = note;
            Write(entry);
        }

        /// <summary>Resultados aún no confirmados por el servidor, del más antiguo al más reciente.</summary>
        public List<JournalEntry> PendingDeliveries()
        {
            lock (_lock)
            {
                return Directory.GetFiles(_directory, "*.json")
                    .Select(f => AgentJson.Deserialize<JournalEntry>(File.ReadAllText(f)))
                    .Where(e => e != null && e.Result != null && !e.Delivered)
                    .OrderBy(e => e.CompletedAt)
                    .ToList();
            }
        }

        public void PurgeDeliveredOlderThan(TimeSpan age)
        {
            lock (_lock)
            {
                var limit = DateTime.Now - age;
                foreach (var file in Directory.GetFiles(_directory, "*.json"))
                {
                    var entry = AgentJson.Deserialize<JournalEntry>(File.ReadAllText(file));
                    if (entry != null && entry.Delivered && entry.DeliveredAt < limit)
                    {
                        File.Delete(file);
                    }
                }
            }
        }

        private void Write(JournalEntry entry)
        {
            lock (_lock)
            {
                var path = PathFor(entry.Uuid);
                var temp = path + ".tmp";
                File.WriteAllText(temp, AgentJson.Serialize(entry, indented: true));

                if (File.Exists(path))
                {
                    File.Replace(temp, path, null);
                }
                else
                {
                    File.Move(temp, path);
                }
            }
        }

        private string PathFor(string uuid)
        {
            if (!Guid.TryParse(uuid, out var guid))
            {
                throw new ArgumentException("UUID de documento inválido: " + uuid);
            }

            return Path.Combine(_directory, guid.ToString("D") + ".json");
        }
    }

    public sealed class JournalEntry
    {
        public string Uuid { get; set; }

        public string Type { get; set; }

        public int? CounterBefore { get; set; }

        public JobResult Result { get; set; }

        public bool Delivered { get; set; }

        public string DeliveryNote { get; set; }

        public DateTime CreatedAt { get; set; }

        public DateTime? StartedAt { get; set; }

        public DateTime? CompletedAt { get; set; }

        public DateTime? DeliveredAt { get; set; }
    }
}
