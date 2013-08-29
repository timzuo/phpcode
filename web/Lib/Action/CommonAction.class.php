<?php

/**
 * 通用接口、通用方法
 *
 * @author Alfred
 */
class CommonAction extends Action {

    /**
     * 对请求参数进行公用判断
     * @param string $fields
     * @param int $errorId
     */
    protected function _doReq($fields, $errorId = 10001) {
        //请求验证的参数
        $fields = explode(',', $fields);
        switch ($errorId) {
            //参数不足
            case 10001:
                foreach ($fields as $v) {
                    if (!isset($_REQUEST[$v])) {
                        $this->_doReqError($errorId);
                    }
                }
                break;

            //参数非法性验证
            case 10002:
                foreach ($fields as $v) {
                    //如果从客户端传了参数进来后做判断
                    if (isset($_REQUEST[$v])) {
                        if (!preg_match("/^[0-9]{1}\d*$/i", $_REQUEST[$v])) {
                            $this->_doReqError($errorId);
                        }
                    }
                }
                break;
        }
    }

    /**
     * 对请求参数进行公用判断
     * @param string $fields
     * @param int $errorId
     */
    protected function _doReqError($errorId) {
        //错误信息
        $errMsg = array(
            10001 => '参数不足',
            10002 => '参数非法',
            10003 => '用户验证不成功',
            10004 => '用户帐号或密码错误',
            10005 => '该verify_code已失效',
            20001 => '团购产品id不能为空',
            20002 => '所请求的团购产品不存在',
            30001 => '用户名只能为3~25位的英文字符、数字或汉字组成',
            30002 => 'Email只可为6~50位的数字、字母、下划线和点组成，且首字符必须为字母或数字',
            30003 => '用户密码由6~25位的数字及英文组成',
            30004 => '密码和Email或用户名不能一致，请重新输入',
            30005 => '用户名或邮箱已存在',
            30006 => '收藏商品id不能为空',
            30007 => '收藏类型有误',
            30008 => '已收藏过此商品',
            30009 => '用户注册不成功',
            30010 => '邮箱已存在',
            40001 => '此订单不存在',
            40002 => '此订单已被晒过',
            40003 => '已经对晒单进行赞操作',
            40004 => '上传图片不成功',
            40005 => '此晒单贴不存在',
            40006 => '对晒单赞操作失败',
            40007 => '对此晒单评论失败',
            40008 => '选择上传图片',
            40009 => '晒单不成功',
            40010 => '此贴子信息不存在或已经被删除',
            70001 => '所请求的商品信息不存在',
            70002 => '所请求的商城暂无评论',
            70003 => '所请求的商城暂无返利信息',
            70004 => '所请求的商城分类暂无信息',
            80001 => '所请求的商品一页最多不能超过40条',
            80002 => '商品价格信息格式错误',
            80003 => '商品过滤信息格式错误',
        );
        $rs = array();
        $rs['is_succ'] = 0;
        $rs['r_type'] = $errorId;
        $rs['r_msg'] = $errMsg[$errorId];
        echo zipString(json_encode($rs));
        exit;
    }

    /**
     * AJAX返回错误信息
     *
     * @var int $code
     */
    protected function _errorReturn($code = 0) {
        if (is_array($code)) {
            $code = $code ? $code[0] : 0;
        }

        $info = 'error';

        if (isset($_REQUEST['jsoncallback'])) {
            return $this->jsonpReturn($code, $info, 0);
        }
        return $this->ajaxReturn($code, $info, 0);
    }

    /**
     * AJAX返回成功信息
     *
     * @param json $data
     */
    protected function _successReturn($data = '') {
        if (isset($_REQUEST['jsoncallback'])) {
            return $this->jsonpReturn($data, 'success', 1);
        }
        $this->ajaxReturn($data, 'success', 1);
    }


}
