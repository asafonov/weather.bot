<?php

class RichText {
  private $blocks = [];

  function header ($level, $text) {
    $this->blocks[] = [
      'type' => 'heading',
      'size' => $level,
      'text' => $text
    ];

    return $this;
  }

  function h1 ($text) {
    return $this->header(1, $text);
  }

  function p ($text) {
    $this->blocks[] = [
      'type' => 'paragraph',
      'text' => $text
    ];

    return $this;
  }

  function get() {
    return $this->blocks;
  }
}
