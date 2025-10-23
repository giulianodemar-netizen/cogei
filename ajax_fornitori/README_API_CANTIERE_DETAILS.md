# API Endpoint: Dettagli Cantiere

## Endpoint
`POST /cogei/ajax_fornitori/get_cantiere_details.php`

## Descrizione
Questo endpoint restituisce i dettagli completi di un cantiere, includendo tutte le informazioni delle aziende associate, dei loro operai con documenti e certificazioni, dei mezzi e delle attrezzature.

## Parametri Richiesti

### POST Parameters
| Parametro | Tipo | Obbligatorio | Descrizione |
|-----------|------|--------------|-------------|
| `cantiere_id` | integer | Sì | ID univoco del cantiere |

### Esempio Richiesta
```javascript
const formData = new FormData();
formData.append('cantiere_id', 123);

fetch('/cogei/ajax_fornitori/get_cantiere_details.php', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => console.log(data));
```

## Risposta

### Struttura JSON della Risposta di Successo

```json
{
  "success": true,
  "cantiere": {
    "id": 123,
    "nome": "Cantiere Via Roma",
    "descrizione": "Ristrutturazione edificio storico",
    "stato": "attivo",
    "data_inizio": "2025-01-15",
    "data_fine": "2025-12-31",
    "data_creazione": "2025-01-10 14:30:00",
    "aziende_assegnate": 3
  },
  "statistiche_globali": {
    "totale_aziende": 3,
    "totale_operai": 15,
    "totale_mezzi": 8,
    "totale_attrezzature": 12,
    "operai_con_formazioni": 12,
    "percentuali": {
      "antincendio": 80.0,
      "primo_soccorso": 73.3,
      "preposti": 33.3
    },
    "competenze_conteggi": {
      "antincendio": 12,
      "primo_soccorso": 11,
      "preposti": 5
    },
    "conforme": true,
    "conformita_percentuale": 80.0
  },
  "aziende": [
    {
      "azienda": {
        "id": 456,
        "nome": "Edilizia SRL",
        "email": "edilizia@example.com",
        "tipo": "Lavoro",
        "data_assegnazione": "2025-01-15 10:00:00",
        "note": "Azienda principale",
        "conformita_percentuale": 85.7,
        "operai_totali": 7,
        "operai_con_formazioni": 6,
        "automezzi_totali": 4,
        "attrezzi_totali": 6
      },
      "operai": [
        {
          "id": 1,
          "personale_id": 101,
          "nome": "Mario",
          "cognome": "Rossi",
          "nome_completo": "Mario Rossi",
          "data_nascita": "1985-05-15",
          "eta": 39,
          "competenze": ["antincendio", "primo_soccorso", "preposti"],
          "ha_formazioni": true,
          "data_assegnazione": "2025-01-15 10:00:00",
          "documenti": [
            {
              "name": "UNILAV",
              "type": "documento_personale",
              "url": "https://example.com/uploads/unilav_12345.pdf",
              "uploaded_at": null,
              "expires_at": "2026-01-15",
              "emission_date": "2025-01-10"
            },
            {
              "name": "Idoneità Sanitaria",
              "type": "certificazione_medica",
              "url": "https://example.com/uploads/idoneita_12345.pdf",
              "uploaded_at": null,
              "expires_at": "2026-06-30"
            },
            {
              "name": "Formazione Antincendio",
              "type": "formazione",
              "url": "https://example.com/uploads/antincendio_12345.pdf",
              "uploaded_at": null,
              "expires_at": "2030-03-15",
              "emission_date": "2025-03-15"
            },
            {
              "name": "Formazione Primo Soccorso",
              "type": "formazione",
              "url": "https://example.com/uploads/primo_soccorso_12345.pdf",
              "uploaded_at": null,
              "expires_at": "2028-02-20",
              "emission_date": "2025-02-20"
            },
            {
              "name": "Formazione Preposti",
              "type": "formazione",
              "url": "https://example.com/uploads/preposti_12345.pdf",
              "uploaded_at": null,
              "expires_at": "2030-01-10",
              "emission_date": "2025-01-10"
            },
            {
              "name": "Formazione Generale e Specifica",
              "type": "formazione",
              "url": "https://example.com/uploads/gen_spec_12345.pdf",
              "uploaded_at": null,
              "expires_at": "2030-12-31",
              "emission_date": "2025-01-01"
            },
            {
              "name": "RSPP",
              "type": "ruolo_sicurezza",
              "url": "https://example.com/uploads/rspp_12345.pdf",
              "uploaded_at": null,
              "expires_at": "2030-06-30",
              "emission_date": "2025-01-01"
            },
            {
              "name": "RLS",
              "type": "ruolo_sicurezza",
              "url": "https://example.com/uploads/rls_12345.pdf",
              "uploaded_at": null,
              "expires_at": "2028-12-31",
              "emission_date": "2025-01-01"
            },
            {
              "name": "Formazione PLE",
              "type": "formazione_specifica",
              "url": "https://example.com/uploads/ple_12345.pdf",
              "uploaded_at": null,
              "expires_at": "2030-04-30",
              "emission_date": "2025-04-01"
            },
            {
              "name": "Formazione Carrelli Elevatori",
              "type": "formazione_specifica",
              "url": "https://example.com/uploads/carrelli_12345.pdf",
              "uploaded_at": null,
              "expires_at": "2030-05-15",
              "emission_date": "2025-05-01"
            }
          ],
          "formazioni": {
            "antincendio": true,
            "primo_soccorso": true,
            "preposti": true,
            "generale_specifica": true,
            "ple": true,
            "carrelli": true,
            "lavori_quota": false,
            "dpi_terza_categoria": false,
            "ambienti_confinati": false
          },
          "ruoli": {
            "rspp": true,
            "rls": true,
            "aspp": false
          }
        }
      ],
      "mezzi": [
        {
          "id": 201,
          "descrizione": "Furgone Transit",
          "targa": "AB123CD",
          "tipologia": "AUTOCARRO",
          "scadenza_revisione": "2026-03-15",
          "scadenza_assicurazione": "2025-12-31",
          "scadenza_verifiche_periodiche": null,
          "data_creazione": "2025-01-10 09:00:00",
          "data_aggiornamento": "2025-01-10 09:00:00",
          "data_assegnazione": "2025-01-15 10:00:00",
          "documenti": [
            {
              "name": "Libretto/Carta di Circolazione",
              "type": "documento_mezzo",
              "url": "https://example.com/uploads/libretto_AB123CD.pdf",
              "uploaded_at": null,
              "expires_at": null
            },
            {
              "name": "Assicurazione",
              "type": "assicurazione",
              "url": "https://example.com/uploads/assicurazione_AB123CD.pdf",
              "uploaded_at": null,
              "expires_at": "2025-12-31"
            }
          ]
        },
        {
          "id": 202,
          "descrizione": "Gru Mobile 25t",
          "targa": "EF456GH",
          "tipologia": "AUTOCARRO_GRU",
          "scadenza_revisione": "2025-11-30",
          "scadenza_assicurazione": "2025-10-31",
          "scadenza_verifiche_periodiche": "2025-06-30",
          "data_creazione": "2025-01-10 09:30:00",
          "data_aggiornamento": "2025-01-10 09:30:00",
          "data_assegnazione": "2025-01-15 10:00:00",
          "documenti": [
            {
              "name": "Libretto/Carta di Circolazione",
              "type": "documento_mezzo",
              "url": "https://example.com/uploads/libretto_EF456GH.pdf",
              "uploaded_at": null,
              "expires_at": null
            },
            {
              "name": "Assicurazione",
              "type": "assicurazione",
              "url": "https://example.com/uploads/assicurazione_EF456GH.pdf",
              "uploaded_at": null,
              "expires_at": "2025-10-31"
            },
            {
              "name": "Verifiche Periodiche",
              "type": "verifica_periodica",
              "url": "https://example.com/uploads/verifiche_EF456GH.pdf",
              "uploaded_at": null,
              "expires_at": "2025-06-30"
            }
          ]
        }
      ],
      "attrezzature": [
        {
          "id": 301,
          "descrizione": "Ponteggio metallico 100mq",
          "data_revisione": "2025-08-31",
          "data_creazione": "2025-01-10 10:00:00",
          "data_aggiornamento": "2025-01-10 10:00:00",
          "data_assegnazione": "2025-01-15 10:00:00",
          "documenti": []
        },
        {
          "id": 302,
          "descrizione": "Betoniera 300L",
          "data_revisione": "2025-12-31",
          "data_creazione": "2025-01-10 10:15:00",
          "data_aggiornamento": "2025-01-10 10:15:00",
          "data_assegnazione": "2025-01-15 10:00:00",
          "documenti": []
        }
      ],
      "statistiche": {
        "competenze": {
          "antincendio": 6,
          "primo_soccorso": 5,
          "preposti": 2
        },
        "conformita": 85.7
      }
    }
  ],
  "timestamp": "2025-10-23 10:30:00",
  "timezone": "Europe/Rome",
  "debug": {
    "cantiere_id": 123,
    "wp_loaded": true,
    "tables_checked": true,
    "execution_time": 0.234
  }
}
```

### Struttura JSON della Risposta di Errore

```json
{
  "error": "Descrizione dell'errore",
  "cantiere_id": 123,
  "debug": {
    "wp_loaded": true,
    "current_user": 1,
    "server_info": {
      "php_version": "8.1.0",
      "memory_limit": "512M",
      "max_execution_time": "60"
    }
  }
}
```

## Codici di Stato HTTP

| Codice | Descrizione |
|--------|-------------|
| 200 | Successo - Dati restituiti correttamente |
| 400 | Bad Request - Parametri mancanti o non validi |
| 404 | Not Found - Cantiere non trovato |
| 405 | Method Not Allowed - Metodo HTTP non consentito (usare POST) |
| 500 | Internal Server Error - Errore interno del server |

## Note di Implementazione

### Performance
- L'endpoint utilizza **eager loading** con JOIN per evitare N+1 queries
- Tutte le risorse (operai, mezzi, attrezzature) vengono caricate con una singola query per tipo di risorsa
- Tempo di esecuzione medio: < 500ms per cantieri con 10-20 aziende

### Sicurezza
- ⚠️ **TODO**: Implementare controlli di autorizzazione per verificare che l'utente abbia i permessi per visualizzare i dati sensibili
- ⚠️ **TODO**: Filtrare i dati in base al ruolo dell'utente (BO Admin, Azienda, etc.)
- Tutti i parametri di input vengono sanitizzati con `intval()` e `$wpdb->prepare()`

### Tipi di Documento

#### Per Operai
- `documento_personale`: UNILAV e altri documenti anagrafici
- `certificazione_medica`: Idoneità Sanitaria
- `formazione`: Formazioni base (Antincendio, Primo Soccorso, Preposti, Generale e Specifica)
- `formazione_specifica`: Formazioni specifiche (PLE, Carrelli, Lavori in Quota, DPI, Ambienti Confinati)
- `ruolo_sicurezza`: RSPP, RLS, ASPP

#### Per Mezzi
- `documento_mezzo`: Libretto/Carta di Circolazione
- `assicurazione`: Polizza assicurativa
- `verifica_periodica`: Verifiche periodiche obbligatorie (per gru, PLE, etc.)

#### Per Attrezzature
- Attualmente non sono previsti documenti allegati per le attrezzature

### Tipologie Mezzi
- `AUTO`: Autovetture
- `AUTOCARRO`: Autocarri
- `AUTOCARRO_GRU`: Autocarri con gru
- `PLE`: Piattaforme di Lavoro Elevabili
- `MEZZI_TERRA`: Mezzi per Movimenti Terra

## Modifiche Future Suggerite

1. **Paginazione**: Per cantieri molto grandi con molte risorse, considerare l'implementazione di paginazione
2. **Filtri**: Aggiungere parametri opzionali per filtrare per azienda, tipo di risorsa, etc.
3. **Caching**: Implementare caching Redis/Memcached per cantieri frequentemente consultati
4. **Permessi**: Implementare sistema di autorizzazione granulare
5. **Rate Limiting**: Proteggere l'endpoint da abusi

## Changelog

### v1.0.0 (2025-10-23)
- Implementazione iniziale endpoint completo
- Aggiunto supporto per mezzi con tutti i campi e documenti
- Aggiunto supporto per attrezzature
- Aggiunto supporto per TUTTI i documenti degli operai (UNILAV, Idoneità, Formazioni, RSPP, RLS, ASPP, PLE, Carrelli, etc.)
- Implementato eager loading per performance ottimizzate
- Aggiunto calcolo statistiche globali e per azienda
