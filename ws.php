<?php
//php -q server.php
$host = 'localhost'; //host
$port = '9000'; //porta
$null = NULL; //null

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);//Cria TCP/IP sream socket
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);//reutiliza a porta
socket_bind($socket, 0, $port);//Tomada de ligação para determinado host
socket_listen($socket);//ouve a porta
$clients = array($socket);//criar e adicionar tomada ouvindo a lista

while (true) {// Inicia loop infinito, de modo que nosso script não pára
	$changed = $clients;// Gerenciar múltiplas conexões
	
	@socket_select($changed, $null, $null, 0, 10);// Retorna os recursos de socket em array $mudança
	
		
	if (in_array($socket, $changed)) {// Verificar se há novo socket
		$socket_new = socket_accept($socket); // Aceitar o novo ID de soquete do Usuário Conectado
		$clients[] = $socket_new; // Adiciona socket a matriz do cliente
		$header = socket_read($socket_new, 1024); // Lê os dados enviados pelo socket
		
		$id=array_search($socket_new, $clients);
		
		echo $id." - Conectado \r\n";
		perform_handshaking($header, $socket_new, $host, $port); // aperto de mãos entre servidor e client
		
		
		// Abrir espaço para um novo socket
		$found_socket = array_search($socket, $changed);
		unset($changed[$found_socket]);
		
	}
	
	// Loop através de todos os soquetes ligados
	foreach ($changed as $changed_socket) {
		@$rec=socket_recv($changed_socket, $ms, 1024, 0);
		$id = array_search($changed_socket, $clients);
				
		while( $rec >= 1)
		{
			$txt = unmask($ms);
			$parametro=explode(";", $txt);
			if($parametro[0]=="r"){
				$cdusu=$parametro[1];
				get("conecta.php?cdusu=$cdusu&id=$id");
				$qtd=get("qtdmsg.php?cdusu=$cdusu");
				msgtodos("on;$cdusu");
				$param="q;";
				
				if($qtd>=1){
					send_message($param.$qtd, $id);
					
				}
			}
			
			if($parametro[0]=="a"){
				$arquivo=$parametro[1];
				$retorno=get("$arquivo");
				send_message($retorno, $id);
			}
			
			if($parametro[0]=="m"){
				$id2=$parametro[1];
				$cdpara=$parametro[2];
				$msg=$parametro[3];
				$dthora=$parametro[4];
				send_message("m;".$cdpara.";".$msg.";".$dthora, $id2);
				echo "enviado \r\n";
			}
			
			//desconecta
			if($parametro[0]=="y"){
				$cdusuario=get("cdusuario.php?id=$id");
				$ids=get("desconecta.php?id=$id");
				$ids=explode(";", $ids);
				
				foreach($ids as $id2){//desconecta os ids do usuário
					if(isset($clients[$id2])){
						socket_close($clients[$id2]);
						unset($clients[$id2]);
					}
					msgtodos("off;$cdusuario");
					echo "$id2 - desconectado \r\n";			
				
				}
	
			}
			break 2;
		}
		$buf = @socket_read($changed_socket, 1024, PHP_NORMAL_READ);
		if ($buf === false) { 
			// remove client for $clients array
			$found_socket = array_search($changed_socket, $clients);
			
			socket_close($clients[$found_socket]);
			unset($clients[$found_socket]);
			$cdusuario=get("cdusuario.php?id=$id");
			$iddesc=get("desconecta.php?id=$found_socket&desc=1");
			msgtodos("off;$cdusuario");
			
			echo "$iddesc desc \r\n";
			
		}
	}
	
}

socket_close($socket);

function send_message($msg, $cd)
{
	
	global $clients;
	
	$ms=mask($msg);
		@socket_write($clients[$cd], $ms, strlen($ms));
	
	return true;
}

function msgtodos($msg){
	
	global $clients;
		
	foreach($clients as $cli)
	{	
	
	$ms=mask($msg);
		@socket_write($cli, $ms, strlen($ms));
	}
	
	
}

function unmask($text) {
	$length = ord($text[1]) & 127;
	if($length == 126) {
		$masks = substr($text, 4, 4);
		$data = substr($text, 8);
	}
	elseif($length == 127) {
		$masks = substr($text, 10, 4);
		$data = substr($text, 14);
	}
	else {
		$masks = substr($text, 2, 4);
		$data = substr($text, 6);
	}
	$text = "";
	for ($i = 0; $i < strlen($data); ++$i) {
		$text .= $data[$i] ^ $masks[$i%4];
	}
	return $text;
}

function mask($text)
{
	$b1 = 0x80 | (0x1 & 0x0f);
	$length = strlen($text);
	
	if($length <= 125)
		$header = pack('CC', $b1, $length);
	elseif($length > 125 && $length < 65536)
		$header = pack('CCn', $b1, 126, $length);
	elseif($length >= 65536)
		$header = pack('CCNN', $b1, 127, $length);
	return $header.$text;
}

function perform_handshaking($receved_header,$client_conn, $host, $port)
{
	
	$headers = array();
	$lines = preg_split("/\r\n/", $receved_header);
	foreach($lines as $line)
	{
		$line = chop($line);
		if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))
		{
			$headers[$matches[1]] = $matches[2];
		}
	}

	$secKey = $headers['Sec-WebSocket-Key'];
	$secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
	$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
	"Upgrade: websocket\r\n" .
	"Connection: Upgrade\r\n" .
	"WebSocket-Origin: $host\r\n" .
	"WebSocket-Location: ws://$host:$port/demo/shout.php\r\n".
	"Sec-WebSocket-Accept:$secAccept\r\n\r\n";
	socket_write($client_conn,$upgrade,strlen($upgrade));
	
}

function get($url){
	return file_get_contents("http://127.0.0.1/phpwebsocket/$url");
}
