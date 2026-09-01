<?php
/**
 * Created by PhpStorm.
 * User: zen
 * Date: 8/4/2017
 * Time: 8:35 μμ
 */

class indexController extends protectedController {

    protected $unprotected = ["index"];

    public function index() {
        redirect($this->L("login"));
    }
}