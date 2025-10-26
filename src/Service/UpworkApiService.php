<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UpworkApiService
{
    private HttpClientInterface $httpClient;
    private string $apiKey;
    private string $apiSecret;
    private string $callbackUrl;

    public function __construct(
        string $upworkApiKey,
        string $upworkApiSecret,
        string $upworkCallbackUrl
    ) {
        $this->httpClient = HttpClient::create();
        $this->apiKey = $upworkApiKey;
        $this->apiSecret = $upworkApiSecret;
        $this->callbackUrl = $upworkCallbackUrl;
    }

    public function getAuthorizationUrl(): string
    {
        $state = bin2hex(random_bytes(16));
        
        $params = [
            'response_type' => 'code',
            'client_id' => $this->apiKey,
            'redirect_uri' => $this->callbackUrl,
            'state' => $state
        ];

        return 'https://www.upwork.com/ab/account-security/oauth2/authorize?' . http_build_query($params);
    }

    public function exchangeCodeForToken(string $code): string
    {
        error_log('Exchanging code for token - Code: ' . substr($code, 0, 10) . '...');
        error_log('API Key: ' . substr($this->apiKey, 0, 10) . '...');
        error_log('Callback URL: ' . $this->callbackUrl);
        
        $response = $this->httpClient->request('POST', 'https://www.upwork.com/api/v3/oauth2/token', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type' => 'authorization_code',
                'client_id' => $this->apiKey,
                'client_secret' => $this->apiSecret,
                'redirect_uri' => $this->callbackUrl,
                'code' => $code,
            ],
        ]);

        $statusCode = $response->getStatusCode();
        error_log('Token exchange response status: ' . $statusCode);
        
        $data = $response->toArray();
        error_log('Token exchange response: ' . json_encode($data));
        
        if (!isset($data['access_token'])) {
            throw new \Exception('Failed to get access token: ' . json_encode($data));
        }

        return $data['access_token'];
    }

    public function fetchUserProfile(string $accessToken): array
    {
        error_log('Testing Upwork GraphQL API with token: ' . substr($accessToken, 0, 20) . '...');
        
        // Use Upwork's GraphQL API to search for jobs
        $response = $this->httpClient->request('POST', 'https://api.upwork.com/graphql', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'query' => '
                  query {
                    marketplaceJobPostings(
                      searchType: USER_JOBS_SEARCH,
                      marketPlaceJobFilter: {
                        searchTerm_eq: { andTerms_all: "Symfony" }
                      },
                      sortAttributes: { field: CREATE_TIME, sortOrder: DESC },
                      pagination: { first: 10 }
                    ) {
                      edges {
                        node {
                          id
                          title
                          description
                          createdDateTime
                        }
                      }
                    }
                  }
                '
            ]
        ]);

        $statusCode = $response->getStatusCode();
        error_log('GraphQL API test response status: ' . $statusCode);
        
        try {
            $data = $response->toArray();
            error_log('GraphQL API test response: ' . json_encode($data));
        } catch (\Exception $e) {
            error_log('GraphQL API test response (raw): ' . $response->getContent());
            throw new \Exception('GraphQL API test failed: HTTP ' . $statusCode . ' - Raw response: ' . $response->getContent());
        }
        
        if ($statusCode !== 200) {
            throw new \Exception('GraphQL API test failed: HTTP ' . $statusCode . ' - ' . json_encode($data));
        }

        return $data;
    }

    public function searchJobs(string $accessToken, string $keyword): array
    {
        error_log('Searching jobs for keyword: ' . $keyword);
        
        $response = $this->httpClient->request('POST', 'https://api.upwork.com/graphql', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'query' => '
                  query {
                    marketplaceJobPostings(
                      marketPlaceJobFilter: { 
                        searchExpression_eq: "' . $keyword . '" 
                      },
                      searchType: USER_JOBS_SEARCH,
                      sortAttributes: { field: RECENCY }
                    ) {
                      edges {
                        node {
                          id
                          title
                          description
                          createdDateTime
                          skills {
                            name
                          }
                        }
                      }
                    }
                  }
                '
            ]
        ]);

        $statusCode = $response->getStatusCode();
        error_log('Job search API response status: ' . $statusCode);
        
        $data = $response->toArray();
       
        error_log('Job search response: ' . json_encode($data));
        
        if ($statusCode !== 200) {
            throw new \Exception('Job search failed: HTTP ' . $statusCode . ' - ' . json_encode($data));
        }

        // Extract jobs from GraphQL response
        $jobs = [];
        error_log('GraphQL response structure: ' . json_encode($data));
        
        if (isset($data['data']['marketplaceJobPostings']['edges'])) {
            error_log('Found ' . count($data['data']['marketplaceJobPostings']['edges']) . ' job edges');
            foreach ($data['data']['marketplaceJobPostings']['edges'] as $edge) {
                $node = $edge['node'];
                error_log('Processing job node: ' . json_encode($node));
                $jobs[] = [
                    'id' => $node['id'],
                    'title' => $node['title'],
                    'description' => $node['description'],
                    'postedAt' => $node['createdDateTime'],
                    'url' => 'https://www.upwork.com/jobs/~02' . $node['id'], // Construct URL with ~02 prefix
                    'budget' => 'Not specified', // Not available in this schema
                    'client' => 'Unknown', // Not available in this schema
                    'country' => 'Unknown', // Not available in this schema
                    'skills' => implode(', ', array_column($node['skills'], 'name')),
                    'proposals' => 0 // Not available in this schema
                ];
            }
        } else {
            error_log('No job edges found in response');
            if (isset($data['data']['marketplaceJobPostings'])) {
                error_log('marketplaceJobPostings structure: ' . json_encode($data['data']['marketplaceJobPostings']));
            }
        }

        error_log('Extracted ' . count($jobs) . ' jobs from GraphQL response');
        return $jobs;
    }
}
