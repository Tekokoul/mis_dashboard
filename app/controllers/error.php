<?php

class errorController extends vanillaController {
	public function index($data=[]) {
		$this->render($data, "html", true);
	}
}