<?php

class RichText {
  $blocks = [];

  function header ($level, $text) {
    $this->blocks[] = [
      'type' => 'section_heading',
      'level' => $level,
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
