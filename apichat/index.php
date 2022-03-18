<?php
    
    class socketServer
    {

	    const LISTEN_SOCKET_NUM = 9;
	    const LOG_PATH = "./log/";  //Журнал
	    private $_ip = "socket"; //ip
	    private $_port = 9000;  // Порт должен быть таким же, как номер порта, когда интерфейс создает соединение WebSocket
	    private $_socketPool = array(); // пул сокетов, то есть массив сокетов
	    private $_master = null;    // Создан объект сокета
	
	    public function __construct()
	    {
	        $this->initSocket();
	    }
	
	    // Создаем соединение WebSocket
	    private function initSocket()
	    {
	        try {
	            // Создаем сокет сокета
	            $this->_master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	            // Устанавливаем повторное использование IP и порта, этот порт можно будет повторно использовать после перезапуска сервера;
	            socket_set_option($this->_master, SOL_SOCKET, SO_REUSEADDR, 1);
	            // Связываем адрес и порт
	            socket_bind($this->_master, $this->_ip, $this->_port);
	            // Функция прослушивания использует активный сокет подключения, чтобы стать подключенным сокетом, чтобы процесс мог принимать запросы от других процессов и становиться серверным процессом. В программировании сервера TCP функция прослушивания превращает процесс в сервер и указывает соответствующий сокет, который должен стать пассивным соединением, и количество неопознанных сокетов, которые могут быть сохранены в нем.
	            socket_listen($this->_master, self::LISTEN_SOCKET_NUM);
	        } catch (Exception $e) {
	            $this->debug(array("code: " . $e->getCode() . ", message: " . $e->getMessage()));
	        }
	        // Сохраняем сокет в пул сокетов (помещаем сокет в массив) по умолчанию, сначала помещаем текущего пользователя
	        $this->_socketPool[0] = array('resource' => $this->_master);
	        $pid = getmypid();
	        $this->debug(array('server' => $this->_master , 'started,pid' =>  $pid));
	    }
	
	    // Приостановленный процесс просматривает массив сокетов для получения, обработки и отправки данных
	    public function run()
	    {
	        // Бесконечный цикл до отключения сокета
	        while (true) {
	            try {
	                
	                $write = $except = NULL;
	                // Вынимаем столбец ресурса из массива
	                $sockets = array_column($this->_socketPool, 'resource');
	
	               /* 
	                 $ sockets - это массив файловых дескрипторов.
	                 $ write отслеживает, записывает ли клиент данные или нет. Когда передается NULL, его не волнует, есть ли изменение записи.
	                 $ except - это элемент в $ sockets, чтобы быть вульгарным, передача null означает прослушивание всего
	                 Последний параметр - время ожидания, 0 заканчивается немедленно, n> 1, заканчивается не более чем через n секунд, если в определенном соединении есть новая динамика, она заранее вернет значение null. Если в определенном соединении есть новая динамика, она вернет
	                */
	                // Получаем номер сокета и отслеживаем его статус. Функция socket_select будет возвращать только когда приходит новое сообщение или когда клиент подключается / отключается и продолжает выполнение
	                $read_num = socket_select($sockets, $write, $except, NULL);
	                if (false === $read_num) {
	                    $this->debug(array('socket_select_error', $err_code = socket_last_error(), socket_strerror($err_code)));
	                    return;
	                }
	
	                // проходим по массиву сокетов
	                foreach ($sockets as $socket) {
	
	                    // Если приходит новое соединение
	                    if ($socket == $this->_master) {
	
	                        // Получение сокетного соединения
	                        $client = socket_accept($this->_master);
	                        if ($client === false) {
	                            $this->debug(['socket_accept_error', $err_code = socket_last_error(), socket_strerror($err_code)]);
	                            continue;
	                        }
	                        // Подключаемся и помещаем в пул сокетов
	                        $this->connection($client);
	                    } else {
	
	                        // Получить данные подключенного сокета и вернуть количество байтов, полученных от сокета.
	                        // Первый параметр: ресурс сокета, второй параметр: переменная для хранения полученных данных, третий параметр: длина полученных данных
	                        $bytes = @socket_recv($socket, $buffer, 2048, 0);
	
	                        // Если количество полученных байтов равно 0
	                        if ($bytes == 0) {
	
	                            // Отключить
	                            $recv_msg = $this->disconnection($socket);
	                        } else {
								$this->_socketPool = (array)$this->_socketPool;
	                            // Определяем, есть ли рукопожатие, делаем рукопожатие без рукопожатия и обрабатываем, если рукопожатие уже выполнено
	                            if ($this->_socketPool[(int)$socket]['handShake'] == false) {
	                                // рукопожатие
	                                $this->handShake($socket, $buffer);
	                                continue;
	                            } else {
	                                // анализируем данные от клиента
	                                $recv_msg = $this->parse($buffer);
	                            }
	                        }
	
	                        // echo "<pre>";
	                        // Бизнес-обработка, сборка формата данных, возвращаемых клиенту
	                        $msg = $this->doEvents($socket, $recv_msg);
	                        // print_r($msg);
	
	                        socket_getpeername( $socket  , $address ,$port );
	                        $this->debug(array(
	                            'send_success',
	                            json_encode($recv_msg),
	                            $address,
	                            $port
	                        ));
	                        // Записываем данные, возвращаемые сервером, в сокет
	                        $this->broadcast($msg);
	                    }
	                }
	            } catch (Exception $e) {
	                $this->debug(array("code: " . $e->getCode() . ", message: " . $e->getMessage()));
	            }
	
	        }
	
	    }
	
	    /**
	      * Трансляция данных
	     * @param $data
	     */
	    private function broadcast($data)
	    {
	        foreach ($this->_socketPool as $socket) {
	            if ($socket['resource'] == $this->_master) {
	                continue;
	            }
	            // записываем в сокет
	            socket_write($socket['resource'], $data, strlen($data));
	        }
	    }
	
	    /**
	      * Бизнес-обработка, при которой вы можете управлять базой данных и возвращать данные клиентов; в зависимости от типа собирать данные в разных форматах
	     * @param $socket
	      * @param $ recv_msg данные от клиента
	     * @return string
	     */
	    private function doEvents($socket, $recv_msg)
	    {
	        $msg_type = $recv_msg['type'];
	        $msg_content = $recv_msg['msg'];
	        $response = [];
	        //echo "<pre>";
	        switch ($msg_type) {
	            case 'login':
	            // Войти онлайн информация
	                $this->_socketPool[(int)$socket]['userInfo'] = array("username" => $msg_content, 'headerimg' => $recv_msg['headerimg'], "login_time" => date("h:i"));
	                // Получение последней записи имени
	                $user_list = array_column($this->_socketPool, 'userInfo');
	                $response['type'] = 'login';
	                $response['msg'] = $msg_content;
	                $response['user_list'] = $user_list;
	                //print_r($response);
	
	                break;
	            case 'logout':
	            // Выход из информации
	                $user_list = array_column($this->_socketPool, 'userInfo');
	                $response['type'] = 'logout';
	                $response['user_list'] = $user_list;
	                $response['msg'] = $msg_content;
	                //print_r($response);
	                break;
	            case 'user':
	            // сообщение отправлено
					$this->_socketPool = (array)$this->_socketPool;
	                $userInfo = $this->_socketPool[(int)$socket]['userInfo'];
	                $response['type'] = 'user';
	                $response['from'] = $userInfo['username'];
	                $response['msg'] = $msg_content;
	                $response['headerimg'] = $userInfo['headerimg'];
	                //print_r($response);
	                break;
	        }
	
	        return $this->frame(json_encode($response));
	    }
	
	    /**
	      * Рукопожатие сокета
	     * @param $socket
	      * @param $ буферные данные, полученные клиентом
	     * @return bool
	     */
	    public function handShake($socket, $buffer)
	    {
	        $acceptKey = $this->encry($buffer);
	        $upgrade = "HTTP/1.1 101 Switching Protocols\r\n" .
	            "Upgrade: websocket\r\n" .
	            "Connection: Upgrade\r\n" .
	            "Sec-WebSocket-Accept: " . $acceptKey . "\r\n\r\n";
	
	        // Записываем сокет в буфер
	        socket_write($socket, $upgrade, strlen($upgrade));
	        // Отметить, что рукопожатие было успешным, при следующем получении данных будет использоваться формат кадра данных
	        $this->_socketPool[(int)$socket]['handShake'] = true;
	        socket_getpeername ( $socket  , $address ,$port );
	        $this->debug(array(
	            'hand_shake_success',
	            $socket,
	            $address,
	            $port
	        ));
	        // Отправляем сообщение, чтобы уведомить клиента об успешном рукопожатии
	        $msg = array('type' => 'handShake', 'msg' => 'Рукопожатие прошло успешно');
	        $msg = $this->frame(json_encode($msg));
	        socket_write($socket, $msg, strlen($msg));
	        return true;
	    }
	
	    /**
	      * Инкапсуляция данных кадра
	     * @param $msg
	     * @return string
	     */
	    private function frame($msg)
	    {
	        $frame = [];
	        $frame[0] = '81';
	        $len = strlen($msg);
	        if ($len < 126) {
	            $frame[1] = $len < 16 ? '0' . dechex($len) : dechex($len);
	        } else if ($len < 65025) {
	            $s = dechex($len);
	            $frame[1] = '7e' . str_repeat('0', 4 - strlen($s)) . $s;
	        } else {
	            $s = dechex($len);
	            $frame[1] = '7f' . str_repeat('0', 16 - strlen($s)) . $s;
	        }
	        $data = '';
	        $l = strlen($msg);
	        for ($i = 0; $i < $l; $i++) {
	            $data .= dechex(ord($msg[$i]));
	        }
	        $frame[2] = $data;
	        $data = implode('', $frame);
	        return pack("H*", $data);
	    }
	
	    /**
	      * Парсить данные клиента
	     * @param $buffer
	     * @return mixed
	     */
	    private function parse($buffer)
	    {
	        $decoded = '';
	        $len = ord($buffer[1]) & 127;
	        if ($len === 126) {
	            $masks = substr($buffer, 4, 4);
	            $data = substr($buffer, 8);
	        } else if ($len === 127) {
	            $masks = substr($buffer, 10, 4);
	            $data = substr($buffer, 14);
	        } else {
	            $masks = substr($buffer, 2, 4);
	            $data = substr($buffer, 6);
	        }
	        for ($index = 0; $index < strlen($data); $index++) {
	            $decoded .= $data[$index] ^ $masks[$index % 4];
	        }
	        return json_decode($decoded, true);
	    }
	
	    // Извлечь и зашифровать информацию о Sec-WebSocket-Key
	    private function encry($req)
	    {
	        $key = null;
	        if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $req, $match)) {
	            $key = $match[1];
	        }
	        // шифрование
	        return base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
	    }
	
	    /**
	      * Подключите розетку
	     * @param $client
	     */
	    public function connection($client)
	    {
	        socket_getpeername ( $client  , $address ,$port );
	        $info = array(
	            'resource' => $client,
	            'userInfo' => '',
	            'handShake' => false,
	            'ip' => $address,
	            'port' => $port,
	        );
	        $this->_socketPool[(int)$client] = $info;
	        $this->debug(array_merge(['socket_connect'], $info));
	    }
	
	    /**
	      	     * Отключить 
	     * @param $socket
	     * @return array
	     */
	    public function disconnection($socket)
	    {
	        $recv_msg = array(
	            'type' => 'logout',
	            'msg' => @$this->_socketPool[(int)$socket]['userInfo']['username'],
	        );
	        unset($this->_socketPool[(int)$socket]);
	        return $recv_msg;
	    }
	
	    /**
	      * Журнал
	     * @param array $info
	     */
	    private function debug(array $info)
	    {
	        $time = date('Y-m-d H:i:s');
	        array_unshift($info, $time);
	        $info = array_map('json_encode', $info);
			$file = fopen(self::LOG_PATH . 'websocket_debug.log', "a+");
			fwrite($file, implode(' | ', $info) . "\r\n");
			fclose($file);
	    }
    }
    
    // создание экземпляра вне класса
    $sk = new socketServer();
    // бежать
    $sk -> run();
