#!/bin/bash

NEW_TAG=$(./auto-inc.sh)
echo ${NEW_TAG}
php ./frock app:build --build-version=${NEW_TAG}
git add .
git commit -m "Build version ${NEW_TAG}" --allow-empty
git tag ${NEW_TAG}
git push --tags
