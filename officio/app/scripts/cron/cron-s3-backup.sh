#!/bin/sh

s3cmd --access_key="KEY" --secret_key="SECRET" sync /backup/ "s3://officio-backup/One/"
