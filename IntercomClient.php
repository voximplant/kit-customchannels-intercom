<?php
namespace Intercom;

use Exception;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class IntercomClient
{
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function replyToContact($conversationId, $adminId, $text) {
        $response = $this->client->request('post', '/conversations/' . $conversationId . '/reply', [
            'body' => json_encode([
                'body' => $text,
                'admin_id' => $adminId,
                'message_type' => 'comment',
                'type' => 'admin'
            ])
        ]);

        return $this->parseResponse($response);
    }

    public function searchContact($email) {
        $response = $this->client->request('post', '/contacts/search', [
            'body' => json_encode([
                'query' => [
                    'field' => 'email',
                    'operator' => '=',
                    'value' => $email
                ]
            ])
        ]);

        return $this->parseResponse($response);
    }

    public function assignConversation($conversationId, int $intercomAdminId)
    {
        $response = $this->client->request('post', '/conversations/' . $conversationId . '/parts', [
            'body' => json_encode([
                'message_type' => 'assignment',
                'type' => 'admin',
                'admin_id' => $intercomAdminId,
                'assignee_id' => $intercomAdminId,
            ])
        ]);

        return $this->parseResponse($response);
    }


    public function closeConversation($conversationId, int $intercomAdminId)
    {
        $response = $this->client->request('post', '/conversations/' . $conversationId . '/parts', [
            'body' => json_encode([
                'message_type' => 'close',
                'type' => 'admin',
                'admin_id' => $intercomAdminId,
            ])
        ]);

        return $this->parseResponse($response);
    }

    protected function parseResponse(ResponseInterface $response)
    {
        $responseArray = json_decode($response->getBody()->getContents(), true);

        if ($response->getStatusCode() !== 200) {
            throw new Exception($response->getReasonPhrase(), $response->getStatusCode());
        }

        return $responseArray;
    }

}
