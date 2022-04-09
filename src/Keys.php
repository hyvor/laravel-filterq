<?php

namespace Hyvor\FilterQ;

class Keys
{
    /**
     * @var array{string?: Key} $keys
     */
    public array $keys = [];

    public function add(string $name): Key
    {
        $key = new Key($name);
        $this->keys[$name] = $key;
        return $key;
    }

    public function get(string $name) : ?Key
    {
        return $this->keys[$name] ?? null;
    }
}
