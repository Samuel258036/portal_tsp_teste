<?php
declare(strict_types=1);
session_start();
date_default_timezone_set('America/Sao_Paulo');

const APP_NAME='TSP — Portal';
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function logged(){return isset($_SESSION['user']);}
function require_login(){if(!logged()){header('Location:index.php');exit;}}
function role(){return $_SESSION['user']['role']??'';}
function allow($roles){require_login(); if(!in_array(role(),$roles,true)){http_response_code(403);die('Acesso não autorizado.');}}
function uid(){return $_SESSION['user']['id']??0;}

if(!isset($_SESSION['seeded'])){
$_SESSION['users']=[
 ['id'=>1,'name'=>'Administrador TSP','email'=>'admin@tsp.demo','cpf'=>'11111111111','password'=>'admin123','role'=>'admin','client_id'=>null,'active'=>1],
 ['id'=>2,'name'=>'Gestão TSP','email'=>'gestao@tsp.demo','cpf'=>'22222222222','password'=>'gestao123','role'=>'gestao','client_id'=>null,'active'=>1],
 ['id'=>3,'name'=>'Dr. Advogado TSP','email'=>'adv@tsp.demo','cpf'=>'33333333333','password'=>'adv123','role'=>'adv','client_id'=>null,'active'=>1],
 ['id'=>4,'name'=>'Cliente Demonstração','email'=>'cliente@demo.com','cpf'=>'44444444444','password'=>'cliente123','role'=>'cliente','client_id'=>1,'active'=>1],
];
$_SESSION['clients']=[
 ['id'=>1,'name'=>'Cliente Demonstração','doc'=>'12.345.678/0001-90','type'=>'Pessoa Jurídica','email'=>'cliente@demo.com','phone'=>'(11) 99999-9999','lawyer_id'=>3,'status'=>'Ativo','created'=>'01/10/2026'],
 ['id'=>2,'name'=>'Maria Oliveira','doc'=>'123.456.789-10','type'=>'Pessoa Física','email'=>'maria@demo.com','phone'=>'(11) 98888-1111','lawyer_id'=>3,'status'=>'Ativo','created'=>'03/10/2026']
];
$_SESSION['cases']=[
 ['id'=>1,'client_id'=>1,'lawyer_id'=>3,'number'=>'1023456-78.2025.8.26.0554','title'=>'Processo demonstrativo','area'=>'Cível','court'=>'TJSP','status'=>'Aguardando audiência','next'=>'Audiência de conciliação','date'=>'18/11/2026 14:30','summary'=>'A próxima etapa prevista é a audiência de conciliação. No momento, não há ação necessária do cliente.'],
 ['id'=>2,'client_id'=>2,'lawyer_id'=>3,'number'=>'1009876-12.2026.8.26.0100','title'=>'Acompanhamento contratual','area'=>'Empresarial','court'=>'TJSP','status'=>'Em análise','next'=>'Aguardar manifestação','date'=>'Sem data','summary'=>'A equipe acompanha a análise e publicará novas movimentações relevantes aqui.']
];
$_SESSION['moves']=[
 ['id'=>1,'case_id'=>1,'title'=>'Audiência designada','text'=>'Audiência de conciliação agendada.','internal'=>'Preparar documentação interna.','visible'=>1,'date'=>'05/10/2026'],
 ['id'=>2,'case_id'=>1,'title'=>'Análise interna','text'=>'','internal'=>'Revisar estratégia antes da audiência.','visible'=>0,'date'=>'06/10/2026']
];
$_SESSION['payments']=[
 ['id'=>1,'client_id'=>1,'desc'=>'Honorários — Parcela 3/4','due'=>'10/10/2026','value'=>'750,00','status'=>'Em aberto'],
 ['id'=>2,'client_id'=>1,'desc'=>'Honorários — Parcela 2/4','due'=>'10/09/2026','value'=>'750,00','status'=>'Pago']
];
$_SESSION['tickets']=[
 ['id'=>1,'client_id'=>1,'subject'=>'Dúvida sobre audiência','category'=>'Processo','status'=>'Respondido','date'=>'02/10/2026','text'=>'Preciso levar algum documento?','reply'=>'Leve documento oficial com foto.']
];
$_SESSION['seeded']=1;
}
function next_id($key){$max=0;foreach($_SESSION[$key] as $r)$max=max($max,(int)$r['id']);return $max+1;}
function find_client($id){foreach($_SESSION['clients'] as $c)if($c['id']==$id)return $c;return null;}
function find_user($id){foreach($_SESSION['users'] as $u)if($u['id']==$id)return $u;return null;}
function visible_cases(){
 $all=$_SESSION['cases'];
 if(role()==='cliente') return array_values(array_filter($all,fn($x)=>$x['client_id']==($_SESSION['user']['client_id']??0)));
 if(role()==='adv') return array_values(array_filter($all,fn($x)=>$x['lawyer_id']==uid()));
 return $all;
}
?>