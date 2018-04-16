<?php 
if (! defined ( 'ABSPATH' )) exit ();
class XH_Alipay_Payment_EDD_Api{
    public $id = 'xh_alipay_payment_edd';
    
    public function __construct(){
        add_filter( 'edd_accepted_payment_icons', array( $this, 'register_payment_icon' ), 10, 1 );
    }
  
    public function init(){
        if(!function_exists('edd_get_option')){
            add_action ( 'admin_notices',function(){
                ?>
                <div class="notice notice-error is-dismissible"><b> Alipay:</b><p>请启用EDD插件!</p></div>
                <?php 
            });
            return;
        }
        
        $appkey =edd_get_option('xh_alipay_payment_edd_appsecret');
        
        //return
        if(isset($_GET['payment_id'])&&isset($_GET['hash'])){
            $hash = $this->generate_xh_hash(array(
                    'payment_id'=>$_GET['payment_id']
            ),$appkey);
            
            if($hash==$_GET['hash']){
                edd_empty_cart();
                wp_redirect(apply_filters('edd_success_page_redirect', edd_get_success_page_uri(),$_GET['payment_id']));
                exit;
            }
        }
        
        //notify
        $data = $_POST;
        if(!isset($data['hash']) ||!isset($data['trade_order_id'])){
            return;
        }
  
        if(isset($data['plugins'])&&$data['plugins']!='edd-alipay'){
            return;
        }
        $hash = $this->generate_xh_hash($data,$appkey);
        if($data['hash']!=$hash){
            return;
        }
        
        try{
            if(!edd_is_payment_complete($data['trade_order_id'])){
                $transaction_id = isset($data['transacton_id'])?$data['transacton_id']:'';
                if($transaction_id){
                    update_post_meta($data['trade_order_id'], '_edd_payment_transaction_id', $transaction_id);
                }
                
                edd_update_payment_status($data['trade_order_id'], 'complete');
            }
        }catch(Exception $e){
            //looger
            $logger = new WC_Logger();
            $logger->add( 'xh_wedchat_payment', $e->getMessage() );
        
            $params = array(
                'action'=>'fail',
                'appid'=>edd_get_option('xh_alipay_payment_edd_appid'),
                'errcode'=>$e->getCode(),
                'errmsg'=>$e->getMessage()
            );
        
            $params['hash']=$this->generate_xh_hash($params, $appkey);
            ob_clean();
            print json_encode($params);
            exit;
        }
        
        $params = array(
            'action'=>'success',
            'appid'=>edd_get_option('xh_alipay_payment_edd_appid')
        );
        
        $params['hash']=$this->generate_xh_hash($params, $appkey);
        ob_clean();
        print json_encode($params);
        exit;
    }
    
    public function register_activation_hook(){
        $val =edd_get_option('xh_alipay_payment_edd_title');
        if(empty($val)){
            edd_update_option('xh_alipay_payment_edd_title',__('Alipay Payment',XH_ALIPAY_PAYMENT_EDD));
        }
        
        $val =edd_get_option('xh_alipay_payment_edd_appid');
        if(empty($val)){
            edd_update_option('xh_alipay_payment_edd_appid','20146123713');
        }
        
        $val =edd_get_option('xh_alipay_payment_edd_appsecret');
        if(empty($val)){
            edd_update_option('xh_alipay_payment_edd_appsecret','6D7B025B8DD098C485F0805193136FB9');
        }
        $val =edd_get_option('xh_alipay_payment_edd_transaction_url');
        if(empty($val)){
            edd_update_option('xh_alipay_payment_edd_transaction_url','https://pay.xunhupay.com');
        }
        
        $val =edd_get_option('xh_alipay_payment_edd_exchange_rate');
        if(empty($val)){
            edd_update_option('xh_alipay_payment_edd_exchange_rate','1');
        }
    }
    
    public function edd_settings_gateways($settings){
        $options=array(
            'xh_alipay_payment_edd_settings'=>array(
                'id'   => 'xh_alipay_payment_edd_header',
                'name' => '<h3>' . __( 'Alipay Payment Settings', XH_ALIPAY_PAYMENT_EDD ) . '</h3>',
                'desc' => '',
                'type' => 'header',
            ),
            'xh_alipay_payment_edd_title'=>array(
                'id' => 'xh_alipay_payment_edd_title',
                'name' =>  __( 'Title', XH_ALIPAY_PAYMENT_EDD ),
                'type' => 'text',
            ),
			'xh_alipay_payment_edd_appid' => array(
			        'id'=>'xh_alipay_payment_edd_appid',
					'name'       => __( 'APP ID', XH_ALIPAY_PAYMENT_EDD ),
					'type'        => 'text',
			        'default'=>'20146123713',
                    'desc' =>'测试账户仅支持1元内价格'
			),
			'xh_alipay_payment_edd_appsecret' => array(
			        'id'=>'xh_alipay_payment_edd_appsecret',
					'name'       => __( 'APP Secret', XH_ALIPAY_PAYMENT_EDD ),
					'type'        => 'text',
			         'default'=>'6D7B025B8DD098C485F0805193136FB9',
			),
			'xh_alipay_payment_edd_transaction_url' => array(
			    'id'=>'xh_alipay_payment_edd_transaction_url',
					'name'       => __( 'Transaction Url', XH_ALIPAY_PAYMENT_EDD ),
					'type'        => 'text',
			         'default'=>'https://pay.xunhupay.com',
			    'desc' =>'个人支付宝/微信即时到账，支付网关：https://pay.xunhupay.com  <a href="https://mp.xunhupay.com" target="_blank">获取Appid</a> <br/>
                                                  微信支付宝代收款，需提现，支付网关：https://pay.wordpressopen.com <a href="http://mp.wordpressopen.com " target="_blank">获取Appid</a>'
			),
            'xh_alipay_payment_edd_exchange_rate'=>array(
                'id' => 'xh_alipay_payment_edd_exchange_rate',
                'name' => __( 'exchange rate', XH_ALIPAY_PAYMENT_EDD ),
                'placeholder'=>'1',
                'desc'=>__( 'Please set current currency against Chinese Yuan exchange rate,default 1.', XH_ALIPAY_PAYMENT_EDD ),
                'type' => 'text'
            )
        );
    
        $settings[$this->id]=$options;
        return $settings;
    }
    
    private function http_post($url,$data){
        if(!function_exists('curl_init')){
            throw new Exception('php未安装curl组件',500);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_REFERER,get_option('siteurl'));
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error=curl_error($ch);
        curl_close($ch);
        if($httpStatusCode!=200){
            throw new Exception("invalid httpstatus:{$httpStatusCode} ,response:$response,detail_error:".$error,$httpStatusCode);
        }
         
        return $response;
    }
    
    public function generate_xh_hash(array $datas,$hashkey){
        ksort($datas);
        reset($datas);
         
        $pre =array();
        foreach ($datas as $key => $data){
            if(is_null($data)||$data===''){continue;}
            if($key=='hash'){
                continue;
            }
            $pre[$key]=$data;
        }
         
        $arg  = '';
        $qty = count($pre);
        $index=0;
         
        foreach ($pre as $key=>$val){
            $arg.="$key=$val";
            if($index++<($qty-1)){
                $arg.="&";
            }
        }
        
        return md5($arg.$hashkey);
    }
    
    public  function isWebApp(){
	    if(!isset($_SERVER['HTTP_USER_AGENT'])){
	        return false;
	    }
	
	    $u=strtolower($_SERVER['HTTP_USER_AGENT']);
	    if($u==null||strlen($u)==0){
	        return false;
	    }
	
	    preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/',$u,$res);
	
	    if($res&&count($res)>0){
	        return true;
	    }
	
	    if(strlen($u)<4){
	        return false;
	    }
	
	    preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/',substr($u,0,4),$res);
	    if($res&&count($res)>0){
	        return true;
	    }
	
	    $ipadchar = "/(ipad|ipad2)/i";
	    preg_match($ipadchar,$u,$res);
	    return $res&&count($res)>0;
	}
    
    public function register_payment_icon( $payment_icons ) {
        $payment_icons[XH_ALIPAY_PAYMENT_EDD_URL.'/images/icon.png'] =__('Alipay Payment',XH_ALIPAY_PAYMENT_EDD);
    
        return $payment_icons;
    }
    
    public function currency_filter_before( $formatted, $currency, $price){
        if($currency=='CNY'){
            $formatted = '&yen;' . ' ' . $price;
        }
        return $formatted;
    }
    
    public function currency_filter_after( $formatted, $currency, $price){
       if($currency=='CNY'){ 
            $formatted = $price . ' ' . '&yen;';
       }
       return $formatted;
    }
    
    public function plugin_action_links($links) {
        return array_merge ( array (
            'settings' => '<a href="' . admin_url ( 'edit.php?post_type=download&page=edd-settings&tab=gateways&section='.$this->id ) . '">'.__('Settings',XH_ALIPAY_PAYMENT_EDD).'</a>'
        ), $links );
    }
    
    public function edd_currency_symbol($symbol, $currency ){
        if($currency=='CNY'){
            $symbol= '&yen;';
        }
        
        return $symbol;
    }
    /**
     * 
     * @param array $order
     * @return string
     */
    public function edd_gateway($purchase_data){  
        if( ! wp_verify_nonce( $purchase_data['gateway_nonce'], 'edd-gateway' ) ) {
    		wp_die( __( 'Nonce verification has failed', XH_ALIPAY_PAYMENT_EDD ), __( 'Error',XH_ALIPAY_PAYMENT_EDD ), array( 'response' => 403 ) );
    		return;
    	}
    
    	// Collect payment data
    	$payment_data = array(
    		'price'         => $purchase_data['price'],
    		'date'          => $purchase_data['date'],
    		'user_email'    => $purchase_data['user_email'],
    		'purchase_key'  => $purchase_data['purchase_key'],
    		'currency'      => edd_get_currency(),
    		'downloads'     => $purchase_data['downloads'],
    		'user_info'     => $purchase_data['user_info'],
    		'cart_details'  => $purchase_data['cart_details'],
    		'gateway'       => $purchase_data['post_data']['edd-gateway'],
    		'status'        => ! empty( $purchase_data['buy_now'] ) ? 'private' : 'pending'
    	);
       
	    $payment_id = edd_insert_payment( $payment_data );
        if($payment_id===false){
            edd_set_error(__( 'Payment Error', XH_ALIPAY_PAYMENT_EDD), __('Ops!Something is wrong',XH_ALIPAY_PAYMENT_EDD));
            edd_record_gateway_error( __( 'Payment Error', XH_ALIPAY_PAYMENT_EDD), __('Ops!Something is wrong',XH_ALIPAY_PAYMENT_EDD), $payment_id );
            edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
            return;
        }
        
        $payment_data['order_id']=$payment_id;
        
        $exchange_rate =floatval(edd_get_option('xh_alipay_payment_edd_exchange_rate',1));
        if($exchange_rate<=0){
            $exchange_rate=1;
        }
        
        $total_fee = round(floatval($payment_data['price'])*$exchange_rate, 2);
        $hashkey          = edd_get_option('xh_alipay_payment_edd_appsecret');
        $params = array(
                'payment_id'=>$payment_id
        );
        
        $params['hash']=$this->generate_xh_hash($params, $hashkey);
        
        //处理二级目录问题
    	$siteurl = rtrim(home_url(),'/');
    	$posi =strripos($siteurl, '/');
    	//若是二级目录域名，需要以“/”结尾，否则会出现403跳转
    	if($posi!==false&&$posi>7){
    	    $siteurl.='/';
    	}
    	
        $data=array(
            'version'   => '1.1',//api version
            'lang'       => get_option('WPLANG','zh-cn'),
            'plugins'   => 'edd-alipay',
            'appid'     => edd_get_option('xh_alipay_payment_edd_appid'),
            'trade_order_id'=>  $payment_data['order_id'],
            'payment'   => 'alipay',
            'is_app'    => $this->isWebApp()?'Y':'N',
            'total_fee' => $total_fee,
            'title'     => $this->get_order_title($payment_data),
            'description'=> $this->get_order_desc($payment_data),
            'time'      => time(),
            'notify_url'=> $siteurl,
            'return_url'=>  $siteurl."?".http_build_query($params),
            'callback_url'=>edd_get_checkout_uri(),
            'nonce_str' => str_shuffle(time())
        );
        
       
        $data['hash']     = $this->generate_xh_hash($data,$hashkey);
        $url              = edd_get_option('xh_alipay_payment_edd_transaction_url').'/payment/do.html';
        
        try {
            $response     = $this->http_post($url, json_encode($data));
            $result       = $response?json_decode($response,true):null;
            if(!$result){
                throw new Exception('Internal server error',500);
            }
             
            $hash         = $this->generate_xh_hash($result,$hashkey);
            if(!isset( $result['hash'])|| $hash!=$result['hash']){
                throw new Exception(__('Invalid sign!',XH_Alipay_Payment),40029);
            }
        
            if($result['errcode']!=0){
                throw new Exception($result['errmsg'],$result['errcode']);
            }
            //edd_empty_cart();
            wp_redirect( $result['url']);
            exit;
        } catch (Exception $e) {
            edd_set_error(__( 'Payment Error', XH_Alipay_Payment), "errcode:{$e->getCode()},errmsg:{$e->getMessage()}");
            edd_record_gateway_error(__( 'Payment Error', XH_Alipay_Payment),"errcode:{$e->getCode()},errmsg:{$e->getMessage()}", $payment_id );
            edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
            return;
        }
    }
    
    public  function edd_settings_sections_gateways( $gateway_sections ) {
        $gateway_sections[$this->id] =  __('Alipay Payment',XH_ALIPAY_PAYMENT_EDD) ;
    
        return $gateway_sections;
    }
    
   public function edd_currencies( $currencies ) {
        $currencies['CNY'] = __('Chinese Yuan(&yen;)', XH_ALIPAY_PAYMENT_EDD);
    
        return $currencies;
    }
    
    public function edd_payment_gateways($gateways){
        $gateways[$this->id] = array( 
            'admin_label' => __('Alipay Payment',XH_ALIPAY_PAYMENT_EDD), 
            'checkout_label' =>edd_get_option('xh_alipay_payment_edd_title', __( 'Alipay Payment', XH_ALIPAY_PAYMENT_EDD ))           
        );
        return $gateways;
    }
    
    public function get_order_title($order, $limit = 98) {
        $subject = "#{$order['order_id']}";
        
        if($order['cart_details']&&count($order['cart_details'])>0){
            $index=0;
            foreach ($order['cart_details'] as $item){
                $subject.= "|{$item['name']}";
                if($index++>0){
                    $subject.='...';
                    break;
                }
            }
        }
    
        $title = mb_strimwidth($subject, 0, $limit,'utf-8');
        return apply_filters('xh-payment-get-order-title', $title,$order);
    }
 
    public function get_order_desc($order) {
        $descs=array();
        
        if( $order['cart_details']){
            foreach ( $order['cart_details'] as $order_item){
                $result =array(
                    'order_item_id'=>$order_item['id'],
                    'qty'=>$order_item['quantity'],
                    'product_id'=>$order_item['id']
                );
                
                if(isset( $result['product_id'])){
                    $post = get_post($result['product_id']);
                    if($post){
                        //获取图片
                        $full_image_url = wp_get_attachment_image_src( get_post_thumbnail_id($post->ID), 'full');
                    
                        $desc=array(
                            'id'=>$result['product_id'],
                            'order_qty'=>$order_item['quantity'],
                            'order_item_id'=>$order_item['id'],
                            'url'=>get_permalink($post),
                            'sale_price'=>$order_item['subtotal'],
                            'image'=>count($full_image_url)>0?$full_image_url[0]:'',
                            'title'=>$post->post_title,
                            'sku'=>$post->ID,
                            'summary'=>$post->post_excerpt,
                            'content'=>$post->post_content
                        );
                    }
                }
                	
                $descs[]=$desc;
            }
        }
       
        return apply_filters('xh-payment-get-order-desc', json_encode($descs),$order);
    }
}
?>
