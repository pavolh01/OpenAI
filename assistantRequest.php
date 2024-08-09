<?php

class OpenAIAssistant
{
    private $apiKey;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    private function makeRequest($url, $method = 'POST', $data = null, $headers = [])
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $defaultHeaders = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2'
        ];

        $allHeaders = array_merge($defaultHeaders, $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }

        curl_close($ch);

        return json_decode($response, true);
    }

    public function createThread()
    {
        $url = 'https://api.openai.com/v1/threads';
        return $this->makeRequest($url);
    }

    public function addMessageToThread($threadId, $message)
    {
        $url = "https://api.openai.com/v1/threads/$threadId/messages";
        $data = [
            'role' => 'user',
            'content' => $message
        ];

        return $this->makeRequest($url, 'POST', $data);
    }

    public function runThread($threadId, $assistantId)
    {
        $url = "https://api.openai.com/v1/threads/$threadId/runs";
        $data = [
            'assistant_id' => $assistantId
        ];

        return $this->makeRequest($url, 'POST', $data);
    }

    public function getRunStatus($threadId, $runId)
    {
        $url = "https://api.openai.com/v1/threads/$threadId/runs/$runId";
        return $this->makeRequest($url, 'GET');
    }

    public function getMessages($threadId)
    {
        $url = "https://api.openai.com/v1/threads/$threadId/messages";
        return $this->makeRequest($url, 'GET');
    }
}

// Replace with your OpenAI API key and Assistant ID
$apiKey = '';
$assistantId = '';

$assistant = new OpenAIAssistant($apiKey);

// Step 1: Create a new thread
$threadResponse = $assistant->createThread();
if (isset($threadResponse['id'])) {
    $threadId = $threadResponse['id'];
    echo "Thread created with ID: $threadId\n";
} else {
    echo "Error creating thread.\n";
    exit;
}

// Step 2: Add a message to the thread
$message = "Co je benzina ?";
$messageResponse = $assistant->addMessageToThread($threadId, $message);
if (isset($messageResponse['id'])) {
    echo "Message added to thread.\n";
} else {
    echo "Error adding message to thread.\n";
    exit;
}

// Step 3: Run the thread with your assistant
$runResponse = $assistant->runThread($threadId, $assistantId);
if (isset($runResponse['id'])) {
    $runId = $runResponse['id'];
    echo "Thread run initiated with ID: $runId. Checking status...\n";

    // Poll for the status with increased duration
    $status = 'queued';
    $maxAttempts = 40; // Increased number of attempts (200-second timeout)
    $attempts = 0;

    while ($status == 'queued' || $status == 'in_progress') {
        sleep(5); // Wait for 5 seconds before checking again
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
            print_r($statusResponse); // This will print the full response for debugging
            break;
        }

        if ($attempts >= $maxAttempts) {
            echo "Timeout reached. Exiting...\n";
            break;
        }
    }

    // Step 4: Retrieve and display messages from the thread
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
