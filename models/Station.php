<?php
namespace Model;
use Entity\Plug;
require_once 'Entity/Card.php';
require_once 'Entity/Plug.php';
require_once 'Entity/Station.php';
require_once 'Entity/User.php';
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
        $this->station = new \Entity\Station;
        $this->station->setId($id);
    }

    /** Меняет статус розетки пример:
     *  {"model":"station","action":"station_control","data":{"station":"1", "plug": 1, "status": true }}
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
     * {"model":"station","action":"card_found","data":{"id":"0000001"}}
     * @param $data
     * @return mixed
     */
    public static function CardFound($data)
    {
        $parse = unpack('C*',$data->id);
        $cardInfo = ['balance' => 100, 'admin' => 1 ];
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
        $card = $this->station->getCard();
        $user = $card->getUser();
        $newBalance = $user->getBalance() - 1;
        $user->setBalance($newBalance);
        $response = [
            'message' => self::prepareMessage('EDIT'.$card->getCode().$newBalance.$user->getIsAdmin()),
            'client' => 'all'
        ];

        return $response;
    }

    /** При окончании потребления тока по розеткам
     * @param $data
     */
    public function Stop($data)
    {
        $this->station->setPlugs(
            (new Plug)
                ->setPlugStatus($data->id, Plug::STATUS_OPEN)
        );

        $log = new \Log();
        $message = $this->station->getMessageId();
        $message .= 'Окончание потребления тока'.PHP_EOL;
        $message .= 'Потреблённая мощность - ' . $data->power . ' кВт*час';
        $log->add(get_called_class(), $message);
    }

    public function OverCurrent($data)
    {
        $log = new \Log();
        $log->add(__CLASS__,'Превышение максимально допустимого тока по розетке #' . $data->id);
    }


    public function OpenCase($data)
    {
        $log = new \Log();
        $log->add(__CLASS__,'Обнаружено вскрытие станции #' . $data->id);
    }

    public function Waiting($data)
    {
        $log = new \Log();
        $log->add(__CLASS__,'Переход станции в режим ожидания потребления тока (9 вольт на пилоте) по розетке #' . $data->id);
    }

    public function Fault($data)
    {
        $log = new \Log();
        $log->add(__CLASS__,'Обнаружение утечки по розетке #' . $data->id);
    }


    public function ErrorLock($data)
    {
        $log = new \Log();
        $log->add(__CLASS__,'Обнаружение ошибки закрытия замка по розетке с пилотом #' . $data->id);
    }

    public function ErrorPp($data)
    {
        $log = new \Log();
        $log->add(__CLASS__,'Обнаружение ошибки кодирующего резистора по розетке с пилотом #' . $data->id);
    }

    public function Block($data)
    {
        $log = new \Log();
        $log->add(__CLASS__,'Успешная обработка комманды о блокировке розетки с пилотом #' . $data->id);
    }

    public function Unblock($data)
    {
        $log = new \Log();
        $log->add(__CLASS__,'Успешноая обработка комманды об окончании блокировке розетки с пилотом #' . $data->id);
    }

    public function Battery()
    {
        $log = new \Log();
        $log->add(__CLASS__,'Переход на батарейное питание');
    }

    public function Drop()
    {
        $log = new \Log();
        $log->add(__CLASS__,'Обрыв питающих фаз');
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