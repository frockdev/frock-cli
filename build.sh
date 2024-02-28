#!/bin/bash

NEW_TAG=$(./auto-inc.sh)
echo ${NEW_TAG}
git tag ${NEW_TAG}
php ./frock app:build --build-version=${NEW_TAG}
