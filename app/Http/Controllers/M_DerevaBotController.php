<?php


namespace App\Http\Controllers;


use App\Models\Questions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\Poll;
use Telegram\Bot\Objects\PollOption;
use Telegram\Bot\Objects\Update;

class M_DerevaBotController extends Controller
{

    private $telegram;

    public function __construct()
    {
        $this->telegram = new Api();
    }

    public function setWebHook()
    {
        $url = secure_url("m-dereva-bot/" . env('TELEGRAM_BOT_TOKEN') . "/webhook");
//        $url = "https://bd200613.ngrok.io/" . env('TELEGRAM_BOT_TOKEN') . "/webhook";
//        die($url);
        try {
            $this->telegram->setWebhook(['url' => $url]);
        } catch (TelegramSDKException $e) {
            Log::error($e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine());
        }
    }

    public function removeWebhook()
    {
        $this->telegram->removeWebhook();
    }

    public function webhook(Request $request)
    {
        $update = $this->telegram->getWebhookUpdate();
        $message = $update->getMessage();
        if ($update->isType('poll')) {
            return $this->nextQuestion($update);
        }
        if (isset($message)) {
            $username = $message->chat->firstName;
            Cache::put("username.$message->chat->id}", $username);
            $this->setChatId($update);
            //$chatId = $update->getChat()->getId();
            switch ($message->text) {
                case "Begin free quiz":
                    return $this->nextQuestion($update);
                    break;
            }
        }


        return $this->start($update);
    }

    private function getUsername($chatId)
    {
        return Cache::get("username.$chatId");
    }

    private function getChatId(Update $update)
    {
        Log::debug($update->getRawResponse());
        if ($update->isType('message')) {
            return $update->message->chat->id;
        }
        if($update->isType('poll')) {
            Log::debug("chatid poll id");
            Log::debug($update->poll->id);
            $chatId = Cache::get("{$update->poll->id}.chatId");
            Log::debug($chatId);
            return $chatId;
        }
    }

    private function setChatId(Update $update)
    {
        if ($update->isType('message')) {
            $chatId = $update->getMessage()->chat->id;
            $username = $update->getMessage()->chat->firstName;
            Cache::put("$username.chatId}", $chatId);
        }


    }

    private function setChatIdFromPoll(Poll $poll, $chatId)
    {
        Cache::put("{$poll->id}.chatId", $chatId);
    }

    protected function start(Update $update)
    {

        $chatId = $this->getChatId($update);
        $username = $this->getUsername($chatId);
        $text = "Hello, $username! Please select an item from the menu to proceed";
        $keyboard = Keyboard::make()
            ->setResizeKeyboard(true)
            ->row(Keyboard::button(['text' => "Begin free quiz"]))
            ->row(Keyboard::button(['text' => "Register"]));
//            ->row(Keyboard::button(['text' => "Learning stages"]))
//            ->row(Keyboard::button(['text' => "Events"]))
//            ->row(Keyboard::button(['text' => 'Contacts']));
        return $this->telegram->sendMessage(
            [
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => $keyboard
            ]
        );
    }

    protected function nextQuestion(Update $update)
    {
        $quiz = null;

        if ($update->isType('poll')) {
            $quiz = new Poll($update->poll);
        }
        $chatId = $this->getChatId($update);

        $username = $this->getUsername($chatId);
        $collectionCache = Cache::get("$username.$chatId.collection");
        $collection = collect($collectionCache);
        $skipQuestions = collect([]);
        Log::debug("collection \n" . $collection);
        Log::debug("Quiz \n" . $quiz);
        $answers = [];
        if (!is_null($quiz)) {
            $skipQuestions = collect($collection->pluck('id'));
            $answeredQuestion = $collection->pop();
            $options = $quiz->options;
            foreach ($options as $optionKey => $option) {
                Log::debug($option);
                if ($option['voter_count'] == 1 && $answeredQuestion['answerIndex'] == $optionKey) {
                    $answeredQuestion['score'] = 1;
                }
            }
            $collection->push($answeredQuestion);
            Log::debug($answeredQuestion);
        }
        $rsQuestions = Questions::limit(10)->where('active', 1)->inRandomOrder()->get();
        if ($skipQuestions->count() > 0) {
            $skipArray = $skipQuestions->unique()->all();
            Log::debug($rsQuestions);
            $question = $rsQuestions->whereNotIn('id', $skipArray)->first();
            Log::debug("question");
            Log::debug($question);
        } else {
            $question = $rsQuestions->first();
        }
        Log::debug($question);
        if (is_null($question)) {
            return $this->scoreQuiz($update, $collection);
        }
        $answers = $question->answers;
        Log::debug("Collection obj 126 \n $collection");


        $answersArray = [];
        $correctAnswer = 0;
        foreach ($answers as $key => $answer) {
            $answersArray[$key] = $answer->answer;
            if ($answer->correct) {
                $correctAnswer = $key;
            }
        }
        $duration = 10;
        if (isset($question->duration)) {
            $duration = $question->duration;
        }
        $collection->push(
            [
                'id' => $question->id,
                'question' => $question->question,
                'answerIndex' => $correctAnswer,
                'score' => 0
            ]
        );

        Log::debug(secure_url($question->media));
        Log::debug(Storage::disk('media')->exists($question->media));
        if (Storage::disk('media')->exists($question->media)) {
            if ($question->media_type == Questions::QUESTION_MEDIA_TYPE_IMAGE) {
                Log::debug('image');
                $this->telegram->sendPhoto(
                    [
                        'chat_id' => $chatId,
//                'photo'=> secure_url($question->media)
                        'photo' => InputFile::create($question->media)
                    ]
                );
            }
            if ($question->media_type == Questions::QUESTION_MEDIA_TYPE_VIDEO) {
                Log::debug('video');
                $this->telegram->sendVideo(
                    [
                        'chat_id' => $chatId,
//                        'video' => InputFile::create($question->media),
                        'video' => InputFile::create($question->media),
                        'supports_streaming' => true
                    ]
                );
            }
        }
        Cache::put("$username.$chatId.collection", $collection);
        $response = $this->telegram->sendPoll(
            [
                'chat_id' => $chatId,
                'type' => 'quiz',
                'question' => $question->question,
                'options' => $answersArray,
                'correct_option_id' => $correctAnswer,
                'close_date' => $duration
            ]
        );
        Log::debug($response);
        $this->setChatIdFromPoll($response->poll, $chatId);
    }

    private function scoreQuiz(Update $update, Collection $collection)
    {
        $result = $collection->sum('score');
        $total = $collection->count();
        $percentage = round(($result / $total) * 100);
        $message = "Congratulations you have scored: \n <b> $percentage% ($result / $total)</b>";
        $username = Cache::get('username');
        $chatId = $this->getChatId($update);
        $this->telegram->sendMessage(
            [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML'
            ]
        );

        Cache::forget("$username.$chatId.collection");
        $text = "Hello, $username! Please select an item from the menu to proceed";
        $keyboard = Keyboard::make()
            ->setResizeKeyboard(true)
            ->row(Keyboard::button(['text' => "Begin free quiz"]))
            ->row(Keyboard::button(['text' => "Register"]));

        return $this->telegram->sendMessage(
            [
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => $keyboard
            ]
        );
    }

}
