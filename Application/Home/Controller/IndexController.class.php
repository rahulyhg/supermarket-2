<?php
namespace Home\Controller;
use Think\Controller;
header("Content-type: text/html; charset=utf-8");
class IndexController extends Controller {
    public function index(){
        $this->_isLogin();
        if(IS_GET && I('get.act') == 're'){
            session('goods', null);
        }
        $Goods = M('Goods');
        if(IS_POST){
            $goods_number = $this->_checkinput(I('post.goods_number', null));
            if(!empty($goods_number)) {
                $goods = $Goods->where("goods_number = '$goods_number'")->find();
                if (!empty($goods)) {
                    $n = 1;
                    $sgoods = session('goods');
                    foreach($sgoods as $key => $value){
                        if($goods_number == $value['goods_number']){
                            $sgoods[$key]['goods_num'] ++;
                            $n = 0;
                            break;
                        }
                    }
                    if($n){
                        $goods['goods_num'] = 1;
                        $sgoods[] = $goods;
                    }
                    session('goods', $sgoods);
                }
            }
        }

        $goods = session('goods');
        $mtotal = 0;
        $itotal = 0;
        foreach($goods as $key => $value){
            $mcount = $value['goods_num'] * $value['goods_money'];
            $icount = $value['goods_num'] * $value['goods_int'];
            $mtotal = $mtotal + $mcount;
            $itotal = $itotal + $icount;
            $goods[$key]['mcount'] = $mcount;
            $goods[$key]['icount'] = $icount;
        }
        $arr = array(
                'title' => '收银_零乐购商超',
                'user_name' => session('user_name'),
                'mtotal' => $mtotal,
                'itotal' => $itotal,
                'goods' => $goods,
                'user_where' => session('user_where'),
        );
        $this->assign($arr);
        $this->display();

    }

    //异步修改商品数量
    public function editnum(){
        $this->_isLogin();
        if(IS_POST){
            $num = I('post.num', 0, 'intval');
            $number = I('post.number');
            if($number){
                $sgoods = session('goods');
                foreach ($sgoods as $key => $value) {
                    if($value['goods_number'] == $number){
                        $sgoods[$key]['goods_num'] = $num;
                        break;
                    }
                }
                session('goods', $sgoods);
            }
        }
        exit;
    }

    public function payint(){
        $this->_isLogin();
        if(IS_POST){
            $buyer = I('post.buyer');
            $itotal = I('post.itotal');
            $account = 'tianwei';
            $touser = 'tianwei'; //接受积分的账号
            $arr = array(
                'title' => '支付积分_零乐购商超',
                'user_name' => session('user_name'),
                'buyer' => $buyer,
                'itotal' => $itotal,
                'account' => $account,
                'touser' => $touser
            );
            $this->assign($arr);
            $this->display();
        }
    }

    public function jiesuan(){
        $this->_isLogin();
        if(IS_POST){
            $goods_number = I('post.goods_number');
            $goods_name = I('post.goods_name');
            $goods_money = I('post.goods_money');
            $goods_int = I('post.goods_int');
            $goods_num = I('post.goods_num');
            $itotal = I('post.itotal');
            $mtotal = I('post.mtotal');
            $money = I('post.money');
            $zmoney = I('post.zmoney');
            $buyer = I('post.buyer');
            $user_name = session('user_name');
            $user_where = session('user_where');
            $len = count($goods_number);

            $Shops = M('Shops');
            $shop = $Shops->where("shop_name = '$user_where'")->find();
            //生成流水号 门店编号+日期+今天的第几次交易
            $number = $this->_logsnumber($shop['shop_num']);

            //插入收银记录表
            $Logs = M('Cashlogs');
            $data = array(
                'logs_number' => $number,
                'user_name' => $user_name,
                'user_where' => $user_where,
                'time' => time(),
                'itotal' => $itotal,
                'mtotal' => $mtotal,
                'money' => $money,
                'zmoney' => $zmoney,
                'buyer' => $buyer,
            );

            $cid = $Logs->add($data);
            $Logs_goods = M('Cashlogs_goods');
            for($i=0;$i<$len;$i++){
                $data = array(
                    'cid' => $cid,
                    'logs_number' => $number,
                    'goods_number' => $goods_number[$i],
                    'goods_name' => $goods_name[$i],
                    'goods_money' => $goods_money[$i],
                    'goods_int' => $goods_int[$i],
                    'goods_num' => $goods_num[$i],
                );
            $Logs_goods->add($data);
            }
            session('goods', null); //清除商品缓存
            $this->success('支付完成！', "/index.php/home/index/toprint?id=$cid");
        }
    }

    public function toprint(){
        $this->_isLogin();
        if(IS_GET) {
            $id = I('get.id', 0 , 'intval');
            $Logs = M('Cashlogs');
            if(!$Logs->where("id = $id")->count()){
                $this->error('该交易不存在！');
            }
            $Logs_goods = M('Cashlogs_goods');
            $log = $Logs->where("id = $id")->find();
            $goods = $Logs_goods->where("cid = $id")->select();
            $arr = array(
                'log' => $log,
                'goods' => $goods,
                'title' => '打印小票_零乐购商超',
                'user_name' => session('user_name'),
            );
            $this->assign($arr);
            $this->display();
        }else{
            $this->error('未定义操作！');
        }
    }

    //生成流水号
    public function _logsnumber($numb){
        $time = date('Ymd',time());
        $Logs = M('Cashlogs');
        $n = $Logs->where('date_format(from_UNIXTIME(`time`),"%Y%m%d") = '.$time)->count() + 1;
        $nums = "000".$n;
        $nums = substr($nums, -4);
        $number = $numb.$time.$nums;
        return $number;
    }

    public function login(){
        if(IS_POST){
            $name = $this->_checkinput($_POST['user_name']);
            $pass = md5($this->_checkinput($_POST['password']));
            $user_where = $this->_checkinput(I('post.user_where'));
            $captcha = $this->_checkinput($_POST['captcha']);

            if(!$this->_check_verify($captcha)){
                $this->error('验证码输入错误！');
            }
            if(!$user_where){
                $this->error('请选择门店！');
            }
            $User = M("User");
            $user = $User->where(array('user_name' => $name, 'password' => $pass))->find();
            if(!empty($user)){
                session('isLogin', 1);
                session('uid', $user['uid']);
                session('user_name', $user['user_name']);
                session('tel', $user['tel']);
                session('user_where', $user_where);
                redirect('/index.php/Home/index/index', 0, '页面跳转中...');
            }else{
                $this->error('账号或者密码输入错误！');
            }

        }else {
            if(session('?isLogin') && session('isLogin') == 1){
                redirect('/index.php/Home/index/index', 0, '页面跳转中...');
            }else {
                $Shops = M('Shops');
                $shops = $Shops->select();
                $this->assign('title', '登陆_零乐购商超');
                $this->assign('shops', $shops);
                $this->display();
            }
        }
    }

    function logout(){
        session(null); // 清空当前的session
        //删除cookie中的session id
        if(isset($_COOKIE[session_name()])) {
            cookie(session_name(), null);
        }
        session('[destroy]'); // 销毁session
        $this->success('退出成功！', '/index.php/home/index/login');
    }

    function _isLogin(){
        if(!session('?isLogin') || session('isLogin') != 1){
            $this->error('请先登录', '/index.php/home/index/login', 1);
        }
        return true;
    }

    //过滤表单输入内容
    function _checkinput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    //验证码
    function verifycode(){
        $config = array(
            'fontSize' => 14, // 验证码字体大小
            'length' => 4, // 验证码位数
            'useNoise' => false, // 关闭验证码杂点
            'imageW' => 110,
            'imageH' => 30,
            'fontttf'  => '5.ttf'
        );
        $Verify = new \Think\Verify($config);
        $Verify->entry();
    }

    //验证码验证
    function _check_verify($code, $id = ''){
        $verify = new \Think\Verify();
        return $verify->check($code, $id);
    }
}