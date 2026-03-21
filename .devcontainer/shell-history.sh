#!/usr/bin/env bash

export HISTSIZE="${HISTSIZE:-50000}"
export HISTFILESIZE="${HISTFILESIZE:-50000}"
export SAVEHIST="${SAVEHIST:-50000}"

if [ -n "${ZSH_VERSION:-}" ]; then
  export HISTFILE="/commandhistory/.zsh_history"
  setopt APPEND_HISTORY SHARE_HISTORY HIST_IGNORE_DUPS HIST_REDUCE_BLANKS 2>/dev/null || true
fi

if [ -n "${BASH_VERSION:-}" ]; then
  export HISTFILE="/commandhistory/.bash_history"
  shopt -s histappend 2>/dev/null || true
  PROMPT_COMMAND="history -a${PROMPT_COMMAND:+; ${PROMPT_COMMAND}}"
fi
