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
            Cache::put("username", $username);
            Cache::put("$username.chat_id", $message->chat->id);
            //$chatId = $update->getChat()->getId();
            switch ($message->text) {
                case "Begin free quiz":
                    return $this->nextQuestion($update);
                    break;
            }
        }


        return $this->start($update);
    }

    protected function start(Update $update)
    {
        $username = $update->getChat()->firstName . '_' . $update->getChat()->lastName;
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
                'chat_id' => $update->getChat()->id,
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

        $username = Cache::get('username');
        $collectionCache = Cache::get("$username.collection");
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
        $rsQuestions = Questions::limit(10)->get();
        if ($skipQuestions->count() > 0) {
            $skipArray = $skipQuestions->unique()->all();
            Log::debug($skipArray);
            $question = $rsQuestions->whereNotIn('id', $skipArray)->first();
            Log::debug("question");
            Log::debug($question);


        } else {
            $question = $rsQuestions->first();

        }
        Log::debug($question);
        $answers = $question->answers;
        Log::debug("Collection obj 126 \n $collection");

        if (is_null($question)) {
            $this->scoreQuiz($update, $collection);
        }

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
        $chatId = Cache::get("$username.chat_id");
        if (!isset($chatId)) {
            $chatId = $update->getChat()->id;
        }
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
        Cache::put("$username.collection", $collection);
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

    private function scoreQuiz(Update $update, Collection $collection)
    {
        $result = $collection->sum('score');
        $total = $collection->count();
        $message = "Congratulations you have scored \n <b>$result out of $total</b>";
        $username = Cache::get('username');
        $chatId = Cache::get("$username.chat_id");
        $this->telegram->sendMessage(
            [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML'
            ]
        );

        Cache::put("$username.collection", collect([]));
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
