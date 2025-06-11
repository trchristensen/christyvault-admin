<?php

namespace App\Http\Controllers;

use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SmsWebhookController extends Controller
{
    public function __construct(
        private SmsService $smsService
    ) {}

    /**
     * Handle incoming SMS webhook from Telnyx
     */
    public function handleIncoming(Request $request)
    {
        try {
            // Verify webhook signature for security
            if (!$this->verifyWebhookSignature($request)) {
                Log::warning('Invalid webhook signature', ['ip' => $request->ip()]);
                return response()->json(['error' => 'Invalid signature'], 403);
            }

            $payload = $request->json()->all();
            
            Log::info('SMS webhook received', ['payload' => $payload]);

            // Handle different event types
            $eventType = $payload['data']['event_type'] ?? null;
            
            if ($eventType === 'message.received') {
                return $this->handleIncomingMessage($payload['data']['payload']);
            }
            
            if ($eventType === 'message.sent' || $eventType === 'message.finalized') {
                return $this->handleMessageStatus($payload['data']['payload']);
            }

            Log::info('Unhandled webhook event type', ['event_type' => $eventType]);
            return response()->json(['message' => 'Event processed']);

        } catch (\Exception $e) {
            Log::error('SMS webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->json()->all()
            ]);
            
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle incoming SMS message
     */
    private function handleIncomingMessage(array $payload): \Illuminate\Http\JsonResponse
    {
        $from = $payload['from']['phone_number'] ?? null;
        $message = $payload['text'] ?? null;
        $messageId = $payload['id'] ?? null;

        if (!$from || !$message) {
            Log::warning('Invalid message payload', ['payload' => $payload]);
            return response()->json(['error' => 'Invalid message'], 400);
        }

        Log::info('Processing incoming SMS', [
            'from' => $from,
            'message' => substr($message, 0, 100),
            'message_id' => $messageId
        ]);

        // Process the message and get response
        $response = $this->smsService->processIncomingSms($from, $message);
        
        if ($response) {
            // Send response back to driver
            $this->smsService->sendSms($from, $response);
            
            Log::info('SMS response sent', [
                'to' => $from,
                'response' => substr($response, 0, 100)
            ]);
        }

        return response()->json(['message' => 'Message processed']);
    }

    /**
     * Handle message status updates
     */
    private function handleMessageStatus(array $payload): \Illuminate\Http\JsonResponse
    {
        $messageId = $payload['id'] ?? null;
        $status = $payload['status'] ?? null;
        $to = $payload['to'][0]['phone_number'] ?? null;

        Log::info('Message status update', [
            'message_id' => $messageId,
            'status' => $status,
            'to' => $to
        ]);

        // You could store message delivery status in database here if needed
        // For now, just log it

        return response()->json(['message' => 'Status updated']);
    }

    /**
     * Verify webhook signature for security
     */
    private function verifyWebhookSignature(Request $request): bool
    {
        $webhookSecret = config('sms.telnyx.webhook_secret');
        
        if (!$webhookSecret) {
            // If no webhook secret is configured, skip verification (not recommended for production)
            Log::warning('No webhook secret configured - skipping signature verification');
            return true;
        }

        $signature = $request->header('telnyx-signature-ed25519');
        $timestamp = $request->header('telnyx-timestamp');
        
        if (!$signature || !$timestamp) {
            return false;
        }

        // Telnyx signature verification
        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $timestamp . '|' . $payload, $webhookSecret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Test endpoint for SMS functionality
     */
    public function test(Request $request)
    {
        if (!config('app.debug')) {
            abort(404);
        }

        $phone = $request->input('phone');
        $message = $request->input('message', 'Test message from ChristyVault');

        if (!$phone) {
            return response()->json(['error' => 'Phone number required'], 400);
        }

        $success = $this->smsService->sendSms($phone, $message);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'SMS sent successfully' : 'Failed to send SMS'
        ]);
    }
} 