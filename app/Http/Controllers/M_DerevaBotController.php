<?php


namespace App\Http\Controllers;


use App\Models\Questions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Keyboard\Keyboard;
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
        $chatId = null;
        if ($update->isType('poll')) {
            return $this->nextQuestion($update);
        }
        if (isset($message)) {
            $username = $message->chat->firstName . '_' . $message->chat->lastName;
            Cache::put("username", $username);
            Cache::put("$username.chat_id", $message->chat->id);
            //$chatId = $update->getChat()->getId();
            switch ($message->text) {
                case "Begin free quiz":
                    return $this->nextQuestion($update, $request);
                    break;
            }
        }


        return $this->start($update);
    }

    protected function start(Update $update)
    {
        $name = $update->getMessage()->getFrom()->getFirstName();
//        echo $name; die;
        $text = "Hello, $name! Please select an item from the menu to proceed";
        $keyboard = Keyboard::make()
            ->setResizeKeyboard(true)
            ->row(Keyboard::button(['text' => "Begin free quiz"]))
            ->row(Keyboard::button(['text' => "Register"]));
//            ->row(Keyboard::button(['text' => "Learning stages"]))
//            ->row(Keyboard::button(['text' => "Events"]))
//            ->row(Keyboard::button(['text' => 'Contacts']));
        return $this->telegram->sendMessage(
            [
                'chat_id' => $update->getChat()->getId(),
                'text' => $text,
                'reply_markup' => $keyboard
            ]
        );
    }

    protected function nextQuestion(Update $update)
    {
        Log::debug($update);
        $quiz = new Poll($update);
        $username = Cache::get('username');
        $collectionCache = Cache::get("$username.collection");
        $collection = collect($collectionCache);
        $skipQuestions = [];
        if(isset($quiz->question)) {
            $skipQuestions = $collection->pluck('id');
            $collectionKey = $collection->search($quiz->question);
            Log::debug($collectionKey);
            Log::debug($skipQuestions);
        }
        //Log::debug($update->getMessage()->poll->question);
        $question = Questions::inRandomOrder()->first();
        $answers = $question->answers;

        $answersArray = [];
        $answerOption = new PollOption($update);
        $correctAnswer = 0;
        foreach ($answers as $key => $answer) {
            $answersArray[$key] = $answerOption->text = $answer->answer;
            if ($answer->correct) {
                $correctAnswer = $key;
            }
        }
        $duration = 10;
        if (isset($question->duration)) {
            $duration = $question->duration;
        }
//        Log::debug($quiz->get('id')); die;
        $quiz->options = $answersArray;
        $collection->push([
            'id' => $question->id,
            'question' => $question,
            'answerIndex' => $correctAnswer,
            'score' => 0
        ]);
        $chatId = Cache::get("$username.chat_id");
        Log::debug(secure_url($question->media));
        Log::debug(Storage::disk('media')->exists($question->media));
        if (Storage::disk('media')->exists($question->media)) {
            if ($question->mediaType == Questions::QUESTION_MEDIA_TYPE_IMAGE) {
                $this->telegram->sendPhoto(
                    [
                        'chat_id' => $chatId,
//                'photo'=> secure_url($question->media)
                        'photo' => InputFile::create($question->media)
                    ]
                );
            }
            if ($question->mediaType == Questions::QUESTION_MEDIA_TYPE_VIDEO) {
                $this->telegram->sendVideo(
                    [
                        'chat_id' => $chatId,
                        'video' => InputFile::create($question->media)
                    ]
                );
            }
        }
        return $this->telegram->sendPoll(
            [
                'chat_id' => $chatId,
                'type' => 'quiz',
                'question' => $question->question,
                'options' => $answersArray,
                'correct_option_id' => $correctAnswer,
                'close_date' => $duration
            ]
        );
    }

}
