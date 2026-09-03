<?php

class errorController extends vanillaController {
	// Unknown controller or action. Answers with the real status code and a
	// generic message through the same page the 401/403 answers use. The old
	// version rendered the full signed-in template (47 warnings per hit for
	// an anonymous visitor), echoed the requested path unescaped, and said 200.
	public function index($data=[]) {
		$code = (int)($data['code'] ?? 404);
		if ($code < 400 || $code > 599) { $code = 404; }
		$this->setAnswer($code, (string)($data['message'] ?? "Page not found."));
		exit;
	}
}
