<?php


class ah implements ArrayAccess
{
    private $data;
    public function __construct($data = [])
    {
        $this->data = $data;
    }
    public function get($key)
    {
        return new self($this->data[$key] ?? []);
    }
    public function getAll()
    {
        return $this->data;
    }
    public function filter($callback)
    {
        return new self(array_filter($this->data, $callback));
    }
    public function each($callback)
    {
        foreach ($this->data as $item) {
            $callback($item);
        }
    }
    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

}