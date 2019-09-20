<?php
/**
 * Created by PhpStorm.
 * User: xuce
 * Date: 2019-09-16
 * Time: 17:51
 */

namespace System\Event;


interface  EventInterface
{
    /**
     * 读事件
     */
    const EV_READ = 1;
    /**
     * 写事件
     */
    const EV_WRITE = 2;

    public function add();
}