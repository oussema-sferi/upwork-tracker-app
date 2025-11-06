<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenAiService
{
    private HttpClientInterface $httpClient;
    private string $apiKey;

    public function __construct(string $openAiApiKey)
    {
        $this->httpClient = HttpClient::create();
        $this->apiKey = $openAiApiKey;
    }

    public function generateProposal(string $jobTitle, string $jobDescription): ?string
    {
        try {
            $prompt = "Job Title: {$jobTitle}\n\n";
            $prompt .= "Job Description:\n{$jobDescription}\n\n";
            $prompt .= "Write a 3–4 line Upwork proposal following these instructions. 
            Start directly with something that grabs attention — a quick insight, idea, or confident statement relevant to the job. 
            Avoid greetings, self-introductions, or mentioning years of experience. 
            Make it sound natural, focused, and helpful. 
            End with a short call to action (e.g., suggesting next step or expressing enthusiasm).";

            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => "You are Oussema, a full-stack PHP and Symfony developer who writes short, modern Upwork proposals that sound human and confident — never generic or formal. 
                        You do NOT start with greetings like 'Hi there' or mention years of experience. 
                        You begin with a sharp, engaging line that connects directly to the client’s project or problem. 
                        You focus on ideas, outcomes, and collaboration, not biography. 
                        Write 3–4 concise lines in plain text (no emojis, no bullet points). 
                        Tone: professional, friendly, confident, creative, and solution-oriented."
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 130,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            //dd($statusCode);
            if ($statusCode !== 200) {
                $errorContent = $response->getContent(false);
                
                // Log error with specific handling for 429 (rate limit/quota)
                if ($statusCode === 429) {
                    try {
                        $errorData = json_decode($errorContent, true);
                        $errorMessage = $errorData['error']['message'] ?? 'Rate limit or quota exceeded';
                        
                        if (strpos($errorMessage, 'quota') !== false || strpos($errorMessage, 'billing') !== false) {
                            error_log('OpenAI API quota exceeded. Check billing: ' . $errorMessage);
                        } else {
                            error_log('OpenAI API rate limit exceeded. Please wait before retrying.');
                        }
                    } catch (\Exception $e) {
                        error_log('OpenAI API error (HTTP 429): Rate limit or quota exceeded');
                    }
                } else {
                    error_log('OpenAI API error (HTTP ' . $statusCode . '): ' . substr($errorContent, 0, 200));
                }
                
                return null;
            }

            $data = $response->toArray();            
            if (isset($data['choices'][0]['message']['content'])) {
                return trim($data['choices'][0]['message']['content']);
            }

            error_log('OpenAI API response missing content');
            return null;
        } catch (\Exception $e) {
            error_log('OpenAI API exception: ' . $e->getMessage());
            return null;
        }
    }
}

