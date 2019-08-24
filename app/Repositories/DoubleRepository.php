<?php


namespace App\Repositories;


use App\Console\Commands\DoublePushServer;
use App\DoubleGame;
use App\DoubleGameBet;
use App\Events\DoubleEvent;


class DoubleRepository
{
    private $winNumber;

    public function start()
    {
        $this->changeStatusPlayers();
        $this->checkGame();


        DoubleEvent::dispatch($this->winNumber);
    }

    /***
     * change game to closed
     */
    private function checkGame()
    {
        $game = DoubleGame::where([
            ['status', '=', DoubleGame::DOUBLE_GAME_STATUS_PENDING],
        ])->first();
        if ($game) {
            $game->update(['status' => DoubleGame::DOUBLE_GAME_STATUS_CLOSED]);
        }
        $this->createGame();
    }

    /***
     * return players why win
     * @return mixed
     */
    private function changeStatusPlayers()
    {
        $game = $this->getGame();
        $gameId = $game->id;
        $this->winNumber = $game->game_number;

        $winColor = $this->getColorWin();

        DoubleGameBet::where([
            ['game_id', '=', $gameId],
            ['anticipated_event', '=', $winColor],
        ])->update(['status' => 'win']);
        DoubleGameBet::where([
            ['game_id', '=', $gameId],
            ['anticipated_event', '!=', $winColor],
        ])->update(['status' => 'lose']);

    }

    /***
     * get color of random number
     * @return string
     */
    private function getColorWin()
    {
        $game = $this->getGame();
        switch ($game->game_number) {
            case 0:
                return DoubleGame::DOUBLE_GAME_COLOR_GREEN;
            case $game->game_number <= 7:
                return DoubleGame::DOUBLE_GAME_COLOR_RED;
            case $game->game_number >= 8:
                return DoubleGame::DOUBLE_GAME_COLOR_BLACK;
            default:
                break;
        }
    }

    /***
     * @return mixed
     */
    private function getGame()
    {
        $game = DoubleGame::where([
            ['status', '=', DoubleGame::DOUBLE_GAME_STATUS_PENDING],
        ])->first();
        if ($game) {
            return $game;
        }
        else{
            $this->createGame();
            return $this->getGame();
        }
    }

    /***
     * create game
     */
    private function createGame()
    {
        DoubleGame::create([
            'name' => 'это лишнее',
            'status' => DoubleGame::DOUBLE_GAME_STATUS_PENDING,
            'game_hash' => 'это хэш',
            'game_salt' => 'это соль',
            'game_number' => mt_rand(0, 14),
        ]);
    }

    /***
     * change status money game
     */
    private function changeStatusBet()
    {
        DoubleGameBet::where([
            ['status', '=', DoubleGame::DOUBLE_GAME_STATUS_PENDING],
        ])->update(['status' => DoubleGame::DOUBLE_GAME_STATUS_CLOSED]);
    }

    /***
     * @param $amount
     * @param $color
     * @param $userId
     */
    public function depositMoney($amount, $color, $user)
    {
        $user->withdrawFloat($amount);
        $userId = $user->id;

        $game = $this->getGame();
        $gameId = $game->id;
        $deposit = DoubleGameBet::where([
            ['status', '=', DoubleGame::DOUBLE_GAME_STATUS_PENDING],
            ['anticipated_event', '=', $color],
            ['user_id', '=', $userId],
            ['game_id', '=', $gameId],
        ])->first();

        if ($deposit) {
            $deposit->amount += $amount;
            $deposit->save();
        } else {
            $this->createBet($amount, $color, $userId);
        }
    }

    private function createBet($amount, $color, $userId)
    {
        $game = $this->getGame();
        $gameId = $game->id;
        DoubleGameBet::create([
            'anticipated_event' => $color,
            'amount' => $amount,
            'status' => 'тут статус, победил или нет',
            'user_id' => $userId,
            'game_id' => $gameId,
        ]);
    }
}
