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
            $prompt = "You are a professional freelancer applying for an Upwork job. Generate a compelling, personalized proposal for the following job posting.\n\n";
            $prompt .= "Job Title: {$jobTitle}\n\n";
            $prompt .= "Job Description:\n{$jobDescription}\n\n";
            $prompt .= "Please write a VERY SHORT, modern proposal (not classic/traditional style) that:\n";
            $prompt .= "1. Starts with TWO very attractive, attention-grabbing opening lines that stand out and are NOT classic or generic\n";
            $prompt .= "2. Shows genuine interest in the project\n";
            $prompt .= "3. Briefly highlights relevant experience\n";
            $prompt .= "4. Addresses the client's specific needs\n";
            $prompt .= "5. The ENTIRE proposal must be ONLY 3-4 lines total (not paragraphs, just lines)\n";
            $prompt .= "6. Avoid classic phrases like 'Dear Sir/Madam', 'I am writing to', 'I would like to apply'\n";
            $prompt .= "7. Do NOT use emojis, icons, or symbols (like 🚀, ✅, ⭐, etc.) - use only plain text\n";
            $prompt .= "8. The proposal must sound natural and human-written, NOT AI-generated - avoid overly formal or robotic language\n";
            $prompt .= "Write in a fresh, modern, and engaging tone that grabs attention from the first line. Keep it extremely concise - maximum 4 lines total. Use only plain text, no emojis or symbols. Make it sound genuinely human-written, not like it came from an AI.";

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
                            'content' => 'You are a professional freelancer writing modern, attention-grabbing job proposals for Upwork. Write extremely short proposals (3-4 lines total, not paragraphs) that start with two very attractive opening lines that stand out. Avoid classic/traditional phrases, generic openings, and never use emojis or icons - use only plain text. Write in a natural, human tone that does not sound AI-generated.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 150,
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

