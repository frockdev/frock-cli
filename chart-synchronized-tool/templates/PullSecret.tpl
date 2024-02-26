{{- define "imagePullSecret" }}
{{- printf "{\"auths\": {\"%s\": {\"auth\": \"%s\"}}}" .Values.imagePullCredentials.registry (printf "%s:%s" .Values.imagePullCredentials.username .Values.imagePullCredentials.password | b64enc) | b64enc }}
{{- end }}