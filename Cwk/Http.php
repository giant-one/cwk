<?php
/**
 * Created by PhpStorm.
 * User: xuce
 * Date: 2019-09-16
 * Time: 19:19
 */

namespace Cwk;


class Http extends Core
{
    protected $request = null;
    protected $response = null;

    public function __construct($listenAddr)
    {
        self::$_listenAddr = $listenAddr;
        parent::__construct();
    }
}
