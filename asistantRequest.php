<?php

// Třída OpenAIAssistant
class OpenAIAssistant
{
    private $apiKey;

    // Konstruktor pro inicializaci třídy s API klíčem
    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    // Soukromá funkce pro provedení HTTP požadavku
    private function makeRequest($url, $method = 'POST', $data = null, $headers = [])
    {
        $ch = curl_init();

        // Nastavení URL požadavku
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // Výchozí hlavičky pro požadavek
        $defaultHeaders = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2'
        ];

        // Sloučení výchozích a dodatečných hlaviček
        $allHeaders = array_merge($defaultHeaders, $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);

        // Pokud jsou k dispozici data, přidají se k požadavku
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Provádění požadavku a získání odpovědi
        $response = curl_exec($ch);

        // Kontrola chyb při provádění požadavku
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }

        curl_close($ch);

        // Dekódování odpovědi JSON
        return json_decode($response, true);
    }

    // Veřejná funkce pro vytvoření nového vlákna
    public function createThread()
    {
        $url = 'https://api.openai.com/v1/threads';
        return $this->makeRequest($url);
    }

    // Veřejná funkce pro přidání zprávy do vlákna
    public function addMessageToThread($threadId, $message)
    {
        $url = "https://api.openai.com/v1/threads/$threadId/messages";
        $data = [
            'role' => 'user',
            'content' => $message
        ];

        return $this->makeRequest($url, 'POST', $data);
    }

    // Veřejná funkce pro spuštění vlákna s asistentem
    public function runThread($threadId, $assistantId)
    {
        $url = "https://api.openai.com/v1/threads/$threadId/runs";
        $data = [
            'assistant_id' => $assistantId
        ];

        return $this->makeRequest($url, 'POST', $data);
    }

    // Veřejná funkce pro získání stavu spuštěného vlákna
    public function getRunStatus($threadId, $runId)
    {
        $url = "https://api.openai.com/v1/threads/$threadId/runs/$runId";
        return $this->makeRequest($url, 'GET');
    }

    // Veřejná funkce pro získání zpráv z vlákna
    public function getMessages($threadId)
    {
        $url = "https://api.openai.com/v1/threads/$threadId/messages";
        return $this->makeRequest($url, 'GET');
    }
}

// Nahraďte vlastním OpenAI API klíčem a ID asistenta
$apiKey = '';
$assistantId = '';

$assistant = new OpenAIAssistant($apiKey);

// Krok 1: Vytvoření nového vlákna
$threadResponse = $assistant->createThread();
if (isset($threadResponse['id'])) {
    $threadId = $threadResponse['id'];
    echo "Thread created with ID: $threadId\n";
} else {
    echo "Error creating thread.\n";
    exit;
}

// Krok 2: Přidání zprávy do vlákna
$message = "Co je benzina ?";
$messageResponse = $assistant->addMessageToThread($threadId, $message);
if (isset($messageResponse['id'])) {
    echo "Message added to thread.\n";
} else {
    echo "Error adding message to thread.\n";
    exit;
}

// Krok 3: Spuštění vlákna s asistentem
$runResponse = $assistant->runThread($threadId, $assistantId);
if (isset($runResponse['id'])) {
    $runId = $runResponse['id'];
    echo "Thread run initiated with ID: $runId. Checking status...\n";

    // Opakovaná kontrola stavu s prodlouženým časovým limitem
    $status = 'queued';
    $maxAttempts = 40; // Zvýšený počet pokusů (200 sekundový časový limit)
    $attempts = 0;

    while ($status == 'queued' || $status == 'in_progress') {
        sleep(5); // Počkejte 5 sekund před dalším kontrolováním
        $attempts++;
        $statusResponse = $assistant->getRunStatus($threadId, $runId);

        if (isset($statusResponse['status'])) {
            $status = $statusResponse['status'];
            echo "Current status: $status\n";

            if ($status == 'completed') {
                if (isset($statusResponse['result']) && isset($statusResponse['result']['choices'][0]['message']['content'])) {
                    echo "Run completed. Assistant's response:\n";
                    echo $statusResponse['result']['choices'][0]['message']['content'] . "\n";
                } else {
                    echo "Run completed but no content was returned.\n";
                }
                break;
            } elseif ($status == 'failed') {
                echo "Run failed. Error details:\n";
                print_r($statusResponse['last_error']);
                break;
            }
        } else {
            echo "Error retrieving run status. Full response:\n";
            print_r($statusResponse); // Toto vytiskne celou odpověď pro ladění
            break;
        }

        if ($attempts >= $maxAttempts) {
            echo "Timeout reached. Exiting...\n";
            break;
        }
    }

    // Krok 4: Získání a zobrazení zpráv z vlákna
    $messagesResponse = $assistant->getMessages($threadId);
    if (isset($messagesResponse['data'])) {
        echo "Messages in the thread:\n";
        foreach ($messagesResponse['data'] as $message) {
            echo $message['role'] . ': ';
            if (is_array($message['content'])) {
                foreach ($message['content'] as $contentPart) {
                    if ($contentPart['type'] === 'text') {
                        echo $contentPart['text']['value'];
                    }
                }
            } else {
                echo $message['content'];
            }
            echo "\n";
        }
    } else {
        echo "Error retrieving messages.\n";
        print_r($messagesResponse);
    }

} else {
    echo "Error running thread.\n";
}

