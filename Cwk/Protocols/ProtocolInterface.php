<?php

namespace Cwk\Protocols;

interface ProtocolInterface
{
    public static function decode($data);

    public static function encode($data);
}