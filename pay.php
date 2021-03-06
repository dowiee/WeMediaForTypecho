<?php
include '../../../config.inc.php';
require_once 'libs/spay.php';
require_once 'libs/payjs.php';
$db = Typecho_Db::get();
$prefix = $db->getPrefix();
date_default_timezone_set('Asia/Shanghai');

$action = isset($_POST['action']) ? addslashes($_POST['action']) : '';
if($action=='paysubmit'){
	$feetype = isset($_POST['feetype']) ? addslashes($_POST['feetype']) : '';
	$feecookie = isset($_POST['feecookie']) ? addslashes($_POST['feecookie']) : '';
	$feecid = isset($_POST['feecid']) ? intval(urldecode($_POST['feecid'])) : '';
	$feeuid = isset($_POST['feeuid']) ? intval($_POST['feeuid']) : 0;
	
	$options = Typecho_Widget::widget('Widget_Options');
	$option=$options->plugin('WeMedia');
	$plug_url = $options->pluginUrl;
	
	$queryContent= $db->select()->from('table.contents')->where('cid = ?', $feecid); 
	$rowContent = $db->fetchRow($queryContent);
	
	switch($option->wemedia_paytype){
		case "spay":
			$time=time();
			$pdata['orderNumber']=date("YmdHis",$time) . rand(100000, 999999);
			$pdata['Money']=$rowContent["wemedia_price"];
			$pdata['Notify_url']=$option->spay_wxpay_notify_url;
			$pdata['Return_url']=$option->spay_wxpay_return_url;
			$pdata['SPayId']=$option->spay_wxpay_id;
			
			$ret=spay_wpay_pay($pdata,$option->spay_wxpay_key,$feetype);
			$url=$ret['url'];
			if($url!=''){
				$data = array(
					'feeid'   =>  $pdata['orderNumber'],
					'feecid'   =>  $feecid,
					'feeuid'     =>  $feeuid,
					'feeprice'=>$pdata['Money'],
					'feetype'     =>  $feetype,
					'feestatus'=>0,
					'feeinstime'=>date('Y-m-d H:i:s',$time),
					'feecookie'=>$feecookie
				);
				$insert = $db->insert('table.wemedia_fee_item')->rows($data);
				$insertId = $db->query($insert);
				$json=json_encode(array("status"=>"ok","type"=>"spay","qrcode"=>$url));
				echo $json;
				exit;
			}
			break;
		case "payjs":
			$time=time();
			$arr = [
				'body' => $options->title,               // 订单标题
				'out_trade_no' => date("YmdHis",$time) . rand(100000, 999999),       // 订单号
				'total_fee' => $rowContent["wemedia_price"]*100,             // 金额,单位:分
				'attach'=>$rowContent["wemedia_price"]// 自定义数据
			];
			$payjs = new Payjs($arr,$option->payjs_wxpay_mchid,$option->payjs_wxpay_key,$option->payjs_wxpay_notify_url);
			$res = $payjs->pay();
			$rst=json_decode($res,true);
			if($rst["return_code"]==1){
				$data = array(
					'feeid'   =>  $arr['out_trade_no'],
					'feecid'   =>  $feecid,
					'feeuid'     =>  $feeuid,
					'feeprice'=>$rowContent['wemedia_price'],
					'feetype'     =>  $feetype,
					'feestatus'=>0,
					'feeinstime'=>date('Y-m-d H:i:s',$time),
					'feecookie'=>$feecookie
				);
				$insert = $db->insert('table.wemedia_fee_item')->rows($data);
				$insertId = $db->query($insert);
				$json=json_encode(array("status"=>"ok","type"=>"payjs","qrcode"=>$rst["qrcode"]));
				echo $json;
				exit;
				
			}
			break;
	}
	$json=json_encode(array("status"=>"fail"));
	echo $json;
	exit;
}
?>