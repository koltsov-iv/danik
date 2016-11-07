<?php
namespace Model;
use Entity\Plug;

/**
 * Created by PhpStorm.
 * User: bona
 * Date: 27.10.16
 * Time: 14:51
 */
class Station {

    private $station;

    public function __construct($id)
    {
        $this->station = new \Entity\Station();
    }

    static $array = [
      1 => [
          'id' => '0x030x220xBB0xBB',
          'balance' => '',
          'admin' => '',
      ],
      2 => [
          'id' => '0x630x660xBE0xBB',
          'balance' => '',
          'admin' => '',
      ],
      3 => [
          'id' => '0x840x520x1A0x2B',
          'balance' => '',
          'admin' => '1',
      ],
      4 => [
          'id' => '0x630x630xA90xBB',
          'balance' => '',
          'admin' => '1',
      ],
    ];

    /** Меняет статус розетки пример:
     *  {"model":"station","action":"station_control","data":{"station":"000001", "plug": 1, "status": true }}
     * @param $data
     * @return array
     */
    public static function stationControl($data)
    {
        $state = $data->station ? 'UNBLOCK' : 'BLOCK';
        $response = [
            'client_id' => $data->station,
            'message' => self::prepareMessage($state.'::'.$data->plug)
        ];

        return $response;
    }


    /** При обнаружении карты клиента
     * {"model":"station","action":"card_found","data":{"id":"0x630x630xA90xBB"}}
     * @param $data
     * @return mixed
     */
    public static function CardFound($data)
    {
        $parse = unpack('C*',$data->id);
        $cardInfo = self::$array[1];
        $pack = pack('C*', $cardInfo['balance']);
        $response['message'] = 'CARD'.$data->id. $pack .$cardInfo['admin'].'\r\n';

        return $response;

    }

    /** При начале потребления тока по розеткам
     * @param $data
     * @return array
     */
    public function Start($data)
    {
        $this->station->setPlugs(
            (new Plug)
                ->setPlugStatus($data->id, Plug::STATUS_BUSY)
        );
        $newBalance = $this->station->getCard()->getUser()->getBalance() - 1;
        $this->station->getCard()->getUser()->setBalance($newBalance);
        $code = $this->station->getCard()->getCode();
        $isAdmin = $this->station->getCard()->getUser()->getIsAdmin();
        $response = [
            'message' => self::prepareMessage('EDIT'.$code.$newBalance.$isAdmin),
            'client' => 'all'
        ];

        return $response;
    }

    /** Пакет на остановку заряда:
     *  {"model":"station","action":"station_stop_plug","data":{"station":"000002", "plug": 2}}
     * @param $data
     * @return array
     */
    public static function stationStopPlug($data)
    {
        $response = [
            'client_id' => $data->station,
            'message' => self::prepareMessage('STOP::'.$data->plug)
        ];

        return $response;
    }

    /** Обновление карты - отправляется всем
     *
     * @param $data
     * @return array
     */
    public static function updateCart($data)
    {
        $series = self::$array[$data->id];
        $response = [
            'message' => self::prepareMessage('EDIT'.$series.$data->balance.$data->admin)
        ];

        return $response;
    }

    public static function prepareMessage($message)
    {
        return $message.'/r/n';
    }

}