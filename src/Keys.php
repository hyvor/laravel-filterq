<?php
namespace Hyvor\FilterQ;

class Keys {

    public array $keys = [];

    public function add($name) {
        $key = new Key($name);
        $this->keys[$name] = $key;
        return $key;
    }

    public function get($name) {
        return $this->keys[$name] ?? null;
    }

}