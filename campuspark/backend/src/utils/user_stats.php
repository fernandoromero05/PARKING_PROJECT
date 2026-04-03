<?php
function compute_level(int $tokens): int {
  if ($tokens <= 0) return 1;
  return floor($tokens / 10) + 1;
}