#!/bin/sh

[ "$APP_DEBUG" == 'true' ] && set -x
set -e

if [ ! -z ${GITHUB_WORKSPACE} ]; then
  APP_WORKSPACE=$GITHUB_WORKSPACE
elif [ ! -z ${CI_PROJECT_DIR} ]; then
  APP_WORKSPACE=$CI_PROJECT_DIR
else
  APP_WORKSPACE="/workdir"
fi

if [ "$APP_DEBUG" == 'true' ]
then
  echo "> You will act as user: $(id -u -n)"
  echo "> Your project source directory : $(ls -al $APP_WORKSPACE)"
  echo "> Your Composer Global Configuration : $(composer config --global --list)"
fi

if [ ! -z ${INPUT_PATH} ]; then
  sh -c "cd $APP_WORKSPACE; $(composer config --global home)/vendor/bin/phplint ${INPUT_PATH} ${INPUT_OPTIONS}"
else
  sh -c "cd $APP_WORKSPACE; $(composer config --global home)/vendor/bin/phplint $*"
fi
