<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OpenAI;
use App\Models\TouristSpot;

class ChatbotController extends Controller
{
    public function ask(Request $request)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        $userMessage = $request->input('message');

        // Get relevant tourist spots from DB
        $keywords = explode(' ', strtolower($userMessage));

        $query = TouristSpot::query();
        foreach($keywords as $word) {
            $query->orWhereRaw('LOWER(name) LIKE ?', ["%$word%"])
                ->orWhereRaw('LOWER(description) LIKE ?', ["%$word%"])
                ->orWhereRaw('LOWER(location) LIKE ?', ["%$word%"])
                ->orWhereRaw('LOWER(category) LIKE ?', ["%$word%"]);
        }

        $matchingSpots = $query->take(10)->get();


        $spotList = $matchingSpots->count() 
            ? $matchingSpots->map(fn($spot) => $spot->name . ' - ' . $spot->description)->implode("\n")
            : 'No matching spots available';

        // OpenAI call
        try {
            $client = OpenAI::client(env('OPENAI_API_KEY'));
            $result = $client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "You are a friendly guide for Atharaman website. 
                                    Always greet the user with a 
                                    welcome message to Atharaman. 
                                    Only use the following database places to answer:\n" . $spotList
                    ],
                    [
                        'role' => 'user',
                        'content' => $userMessage
                    ],
                ],
            ]);

            $aiReply = $result->choices[0]->message->content ?? "Sorry, I couldn't generate a reply.";
        } catch (\Exception $e) {
            $aiReply = "Error connecting to AI: " . $e->getMessage();
        }

        return response()->json([
            'reply' => $aiReply,
            'suggestions' => $matchingSpots
        ]);
    }
}
