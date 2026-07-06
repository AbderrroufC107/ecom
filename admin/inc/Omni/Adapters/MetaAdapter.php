<?php
namespace Omni\Adapters;

use Omni\UnifiedMessage;
use Omni\EventLogger;
use Exception;

class MetaAdapter implements AdapterInterface {
    
    public function validateSignature(string $payload, string $signature, string $secret): bool {
        if (!$secret || !$signature) return false; 
        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }

    public function parsePayload(string $payload, int $channelId): array {
        $data = json_decode($payload, true);
        $messages = [];

        if (!isset($data['object']) || ($data['object'] !== 'page' && $data['object'] !== 'instagram')) {
            return [];
        }

        foreach ($data['entry'] as $entry) {
            if (isset($entry['messaging'])) {
                foreach ($entry['messaging'] as $event) {
                    $senderId = $event['sender']['id'] ?? null;
                    if (!$senderId) continue;
                    
                    if (isset($event['message']['is_echo']) && $event['message']['is_echo']) continue;
                    if (isset($event['read']) || isset($event['delivery'])) continue;

                    $msg = new UnifiedMessage([
                        'messageId' => $event['message']['mid'] ?? uniqid('meta_'),
                        'platformUserId' => $senderId,
                        'provider' => 'meta',
                        'channelId' => $channelId,
                        'messageType' => 'TEXT',
                        'text' => $event['message']['text'] ?? '',
                        'timestamp' => date('Y-m-d H:i:s', ($event['timestamp'] ?? time()*1000) / 1000),
                        'metadata' => json_encode($event)
                    ]);

                    // Advanced Media parsing
                    if (isset($event['message']['attachments'])) {
                        $msg->messageType = 'MEDIA';
                        $attachments = $event['message']['attachments'];
                        
                        // Just take the first attachment for now, or aggregate
                        $att = $attachments[0];
                        $type = $att['type'] ?? 'file';
                        
                        if ($type === 'location') {
                            $msg->messageType = 'LOCATION';
                            $lat = $att['payload']['coordinates']['lat'] ?? '';
                            $long = $att['payload']['coordinates']['long'] ?? '';
                            $msg->text = "Location: $lat, $long";
                        } else {
                            $msg->mediaUrl = $att['payload']['url'] ?? null;
                        }

                        if (empty($msg->text) && isset($att['title'])) {
                            $msg->text = $att['title'];
                        }
                    }
                    
                    if (isset($event['postback']['referral'])) {
                        $msg->adId = $event['postback']['referral']['ad_id'] ?? null;
                    } else if (isset($event['message']['referral'])) {
                        $msg->adId = $event['message']['referral']['ad_id'] ?? null;
                    }

                    $messages[] = $msg;
                }
            }
            
            // Comments parsing
            if (isset($entry['changes'])) {
                foreach ($entry['changes'] as $change) {
                    if ($change['field'] === 'feed' || $change['field'] === 'comments') {
                        $val = $change['value'];
                        // Auto-ignore deleted or hidden
                        if (isset($val['verb']) && in_array($val['verb'], ['remove', 'hide', 'block'])) continue;
                        
                        // Ignore self (System) comments
                        if (isset($val['from']['id']) && $val['from']['id'] === $entry['id']) continue;
                        
                        if ($val['item'] === 'comment' && $val['verb'] === 'add') {
                            $msg = new UnifiedMessage([
                                'messageId' => $val['comment_id'],
                                'platformUserId' => $val['from']['id'] ?? 'unknown',
                                'provider' => 'meta',
                                'channelId' => $channelId,
                                'messageType' => 'COMMENT',
                                'text' => $val['message'] ?? '',
                                'postId' => $val['post_id'] ?? null,
                                'commentId' => $val['comment_id'] ?? null,
                                'timestamp' => date('Y-m-d H:i:s', $val['created_time'] ?? time()),
                                'metadata' => json_encode($change)
                            ]);
                            
                            // Check media in comments
                            if (isset($val['photo'])) {
                                $msg->messageType = 'MEDIA';
                                $msg->mediaUrl = $val['photo'];
                            } else if (isset($val['video'])) {
                                $msg->messageType = 'MEDIA';
                                $msg->mediaUrl = $val['video'];
                            }

                            $messages[] = $msg;
                        }
                    }
                }
            }
        }

        return $messages;
    }

    public function sendMessage(int $channelId, string $platformUserId, string $text, array $options = []): bool {
        global $pdo;
        
        $secretManager = new \Security\SecretManager($pdo);
        $eventLogger = new EventLogger($pdo);
        
        $secretName = "meta_{$channelId}_access_token";
        $token = $secretManager->getSecret($secretName);

        if (!$token) {
            $eventLogger->log('Meta Send Error', ['channel' => 'meta', 'status' => 'FAILED', 'metadata' => ['error' => 'No access token']]);
            return false;
        }

        // Determine if this is a Private Reply to a comment or standard Messenger
        $isPrivateReply = $options['is_private_reply'] ?? false;
        
        if ($isPrivateReply) {
            // Private reply requires POSTing to /v19.0/{comment_id}/private_replies
            $url = "https://graph.facebook.com/v19.0/{$platformUserId}/private_replies?access_token=" . $token;
            $payload = ['message' => $text];
        } else {
            // Standard Messenger
            $url = "https://graph.facebook.com/v19.0/me/messages?access_token=" . $token;
            $payload = [
                'recipient' => ['id' => $platformUserId],
                'message' => ['text' => $text]
            ];

            if (isset($options['attachment_url'])) {
                $payload['message'] = [
                    'attachment' => [
                        'type' => 'image',
                        'payload' => ['url' => $options['attachment_url'], 'is_reusable' => true]
                    ]
                ];
            }
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        // Exponential Backoff / Retry Mechanism
        $maxRetries = 3;
        $attempt = 0;
        $success = false;
        $delay = 1; // 1s, 2s, 4s
        $lastResponse = '';

        while ($attempt < $maxRetries && !$success) {
            $startTime = microtime(true);
            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $duration = round((microtime(true) - $startTime) * 1000);
            
            if ($code >= 200 && $code < 300) {
                $success = true;
                $eventLogger->log('Meta Sent', [
                    'channel' => 'meta',
                    'status' => 'SUCCESS',
                    'duration_ms' => $duration,
                    'metadata' => ['payload' => $payload, 'response' => $response]
                ]);
            } else {
                $lastResponse = $response;
                $attempt++;
                
                $eventLogger->log('Meta Send Retry', [
                    'channel' => 'meta',
                    'status' => 'RETRY',
                    'duration_ms' => $duration,
                    'metadata' => ['attempt' => $attempt, 'error' => $response]
                ]);
                
                // Rate limit (4) or Server error (500) check -> wait
                if ($code == 4 || $code >= 500) {
                    sleep($delay);
                    $delay *= 2; // Exponential backoff
                } else {
                    // For auth errors (190) or bad requests (400), no need to retry, it will just fail
                    break;
                }
            }
        }

        curl_close($ch);
        
        if (!$success) {
            $eventLogger->log('Meta Send Failed', [
                'channel' => 'meta',
                'status' => 'FAILED',
                'metadata' => ['final_error' => $lastResponse]
            ]);
        }

        return $success;
    }
}
