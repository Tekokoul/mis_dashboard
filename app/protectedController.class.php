<?php

class protectedController extends vanillaController {

    protected $unprotected = [];

    public function __construct(Registry $registry) {
        $this->R = $registry;
        if($this->isLoggedIn()||$this->allowed()){
            parent::__construct($registry);
        } else {
            $this->setAnswer(401, "You do not have permission to view this.");
        }
    }

    protected function allowed(){
        return in_array($this->R->url['action'],$this->unprotected);
    }
}