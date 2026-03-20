{{/*
Expand the name of the chart.
*/}}
{{- define "ai-community-platform.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Create a default fully qualified app name.
*/}}
{{- define "ai-community-platform.fullname" -}}
{{- if .Values.fullnameOverride }}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- $name := default .Chart.Name .Values.nameOverride }}
{{- if contains $name .Release.Name }}
{{- .Release.Name | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" }}
{{- end }}
{{- end }}
{{- end }}

{{/*
Create chart name and version as used by the chart label.
*/}}
{{- define "ai-community-platform.chart" -}}
{{- printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Common labels
*/}}
{{- define "ai-community-platform.labels" -}}
helm.sh/chart: {{ include "ai-community-platform.chart" . }}
{{ include "ai-community-platform.selectorLabels" . }}
{{- if .Chart.AppVersion }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
{{- end }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end }}

{{/*
Selector labels
*/}}
{{- define "ai-community-platform.selectorLabels" -}}
app.kubernetes.io/name: {{ include "ai-community-platform.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}

{{/*
Create the name of the service account to use
*/}}
{{- define "ai-community-platform.serviceAccountName" -}}
{{- if .Values.serviceAccount.create }}
{{- default (include "ai-community-platform.fullname" .) .Values.serviceAccount.name }}
{{- else }}
{{- default "default" .Values.serviceAccount.name }}
{{- end }}
{{- end }}

{{/*
Database URL helper
*/}}
{{- define "ai-community-platform.databaseUrl" -}}
{{- if .Values.postgresql.enabled }}
postgresql://{{ .Values.postgresql.auth.username }}:{{ .Values.postgresql.auth.password }}@{{ include "ai-community-platform.fullname" . }}-postgresql:5432/{{ .Values.postgresql.auth.database }}
{{- else }}
postgresql://{{ .Values.externalServices.postgresql.username }}:{{ .Values.externalServices.postgresql.password }}@{{ .Values.externalServices.postgresql.host }}:{{ .Values.externalServices.postgresql.port }}/{{ .Values.externalServices.postgresql.database }}
{{- end }}
{{- end }}

{{/*
Redis URL helper
*/}}
{{- define "ai-community-platform.redisUrl" -}}
{{- if .Values.redis.enabled }}
redis://{{ include "ai-community-platform.fullname" . }}-redis-master:6379
{{- else }}
redis://{{ .Values.externalServices.redis.host }}:{{ .Values.externalServices.redis.port }}
{{- end }}
{{- end }}

{{/*
OpenSearch URL helper
*/}}
{{- define "ai-community-platform.opensearchUrl" -}}
{{- if .Values.opensearch.enabled }}
http://{{ include "ai-community-platform.fullname" . }}-opensearch:9200
{{- else }}
http://{{ .Values.externalServices.opensearch.host }}:{{ .Values.externalServices.opensearch.port }}
{{- end }}
{{- end }}

{{/*
RabbitMQ URL helper
*/}}
{{- define "ai-community-platform.rabbitmqUrl" -}}
{{- if .Values.rabbitmq.enabled }}
amqp://{{ .Values.rabbitmq.auth.username }}:{{ .Values.rabbitmq.auth.password }}@{{ include "ai-community-platform.fullname" . }}-rabbitmq:5672
{{- else }}
amqp://{{ .Values.externalServices.rabbitmq.username }}:{{ .Values.externalServices.rabbitmq.password }}@{{ .Values.externalServices.rabbitmq.host }}:{{ .Values.externalServices.rabbitmq.port }}
{{- end }}
{{- end }}