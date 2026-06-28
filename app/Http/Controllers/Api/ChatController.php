<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatController extends ApiController
{
    public function getOrCreateRoom(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['nullable', 'integer'],
            'order_reference' => ['nullable', 'string'],
        ]);

        if (empty($data['order_id']) && empty($data['order_reference'])) {
            return $this->fail('Provide order_id or order_reference.', 422);
        }

        $order = !empty($data['order_reference'])
            ? Order::where('public_reference', $data['order_reference'])->first()
            : Order::find($data['order_id']);

        if (!$order) {
            return $this->fail('Order not found.', 404);
        }

        $user = $request->user('sanctum');

        if ($user) {
            if (!$this->userBelongsToBusiness($user, $order->business_id)) {
                return $this->fail('Unauthorized', 403);
            }
        } elseif (empty($data['order_reference'])) {
            return $this->fail('Unauthorized', 401);
        }

        $room = ChatRoom::firstOrCreate(
            ['order_id' => $order->id],
            [
                'business_id' => $order->business_id,
                'customer_id' => $order->customer_id,
                'seller_id' => $order->business->owner_id,
                'customer_token' => Str::random(40),
                'status' => 'open',
            ]
        );

        return $this->ok([
            'room' => $room,
            'customer_token' => $room->customer_token,
        ], 'Chat room ready');
    }

    public function messages(Request $request, int $room)
    {
        $chatRoom = ChatRoom::findOrFail($room);

        if (!$this->resolveActor($request, $chatRoom)) {
            return $this->fail('Unauthorized', 403);
        }

        $messages = ChatMessage::where('room_id', $chatRoom->id)
            ->oldest('created_at')
            ->paginate($request->integer('per_page', 30));

        return $this->ok($messages, 'Chat messages');
    }

    public function store(Request $request, int $room)
    {
        $chatRoom = ChatRoom::findOrFail($room);
        $actor = $this->resolveActor($request, $chatRoom);

        if (!$actor) {
            return $this->fail('Unauthorized', 403);
        }

        if ($chatRoom->status === 'closed') {
            return $this->fail('This chat room is closed.', 422);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $message = ChatMessage::create([
            'room_id' => $chatRoom->id,
            'sender_id' => $actor['id'],
            'sender_type' => $actor['type'],
            'message' => $data['message'],
        ]);

        broadcast(new MessageSent($message))->toOthers();

        return $this->ok($message, 'Message sent', 201);
    }

    public function markRead(Request $request, int $room)
    {
        $chatRoom = ChatRoom::findOrFail($room);
        $actor = $this->resolveActor($request, $chatRoom);

        if (!$actor) {
            return $this->fail('Unauthorized', 403);
        }

        $otherType = $actor['type'] === 'seller' ? 'customer' : 'seller';

        ChatMessage::where('room_id', $chatRoom->id)
            ->where('sender_type', $otherType)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->ok(null, 'Messages marked as read');
    }

    public function broadcastAuth(Request $request)
    {
        $channelName = (string) $request->input('channel_name');
        $socketId = (string) $request->input('socket_id');

        if (!preg_match('/^private-chat\.(\d+)$/', $channelName, $matches)) {
            return $this->fail('Invalid channel.', 403);
        }

        $chatRoom = ChatRoom::find((int) $matches[1]);

        if (!$chatRoom) {
            return $this->fail('Channel not found.', 404);
        }

        if (!$this->resolveActor($request, $chatRoom)) {
            return $this->fail('Unauthorized', 403);
        }

        $pusher = Broadcast::pusher(config('broadcasting.connections.reverb'));
        $response = $pusher->authorizeChannel($channelName, $socketId);

        return response()->json(json_decode($response, true));
    }

    /**
     * @return array{type: string, id: int}|null
     */
    private function resolveActor(Request $request, ChatRoom $chatRoom): ?array
    {
        /** @var User|null $user */
        $user = $request->user('sanctum');

        if ($user) {
            return $this->userBelongsToBusiness($user, $chatRoom->business_id)
                ? ['type' => 'seller', 'id' => $user->id]
                : null;
        }

        $token = $request->header('X-Chat-Token') ?: $request->input('customer_token');

        if ($token && hash_equals((string) $chatRoom->customer_token, (string) $token)) {
            return ['type' => 'customer', 'id' => $chatRoom->customer_id];
        }

        return null;
    }

    private function userBelongsToBusiness(User $user, int $businessId): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        return DB::table('business_users')
            ->where('user_id', $user->id)
            ->where('business_id', $businessId)
            ->where('status', 'active')
            ->exists();
    }
}
