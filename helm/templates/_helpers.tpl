{{/*
  Helpers for safe PVC resizing.

  Kubernetes does not allow shrinking a PVC. If a user lowers .Values.pvc.*.storage,
  Helm upgrades would fail when trying to patch the PVC.

  We use Helm's `lookup` to read the existing PVC's requested storage and clamp the
  rendered value to the larger of (existing, desired).
*/}}

{{- define "klamm.quantityToBytes" -}}
{{- $q := (default "" . | toString | trim) -}}
{{- $numStr := (regexFind "^[0-9]+(\\.[0-9]+)?" $q) | default "0" -}}
{{- $unit := (regexFind "[a-zA-Z]+$" $q) | default "" -}}
{{- $num := ($numStr | toFloat) -}}

{{- /* Common Kubernetes units for resource quantities (binary + decimal). */ -}}
{{- $factors := dict
  "" 1.0
  "B" 1.0
  "Ki" 1024.0
  "Mi" 1048576.0
  "Gi" 1073741824.0
  "Ti" 1099511627776.0
  "Pi" 1125899906842624.0
  "Ei" 1152921504606846976.0
  "K" 1000.0
  "M" 1000000.0
  "G" 1000000000.0
  "T" 1000000000000.0
  "P" 1000000000000000.0
  "E" 1000000000000000000.0
 -}}

{{- $factor := (get $factors $unit) | default 0.0 -}}
{{- if eq $factor 0.0 -}}
{{- /* Unknown unit: return 0 so caller can decide a safe fallback. */ -}}
0
{{- else -}}
{{- mul $num ($factor | toFloat) -}}
{{- end -}}
{{- end -}}

{{- define "klamm.pvcRequestedStorageNoShrink" -}}
{{- $root := index . 0 -}}
{{- $pvcName := index . 1 -}}
{{- $desired := (index . 2 | default "" | toString | trim) -}}

{{- $existing := (lookup "v1" "PersistentVolumeClaim" $root.Release.Namespace $pvcName) -}}
{{- if not $existing -}}
{{- $desired -}}
{{- else -}}
{{- $existingQty := (get (get (get (get $existing "spec") "resources") "requests") "storage") | default "" | toString | trim -}}
{{- if eq $existingQty "" -}}
{{- $desired -}}
{{- else if eq $desired "" -}}
{{- $existingQty -}}
{{- else -}}
{{- $existingBytes := (include "klamm.quantityToBytes" $existingQty | toFloat) -}}
{{- $desiredBytes := (include "klamm.quantityToBytes" $desired | toFloat) -}}

{{- /*
  If parsing fails (bytes=0), prefer the existing value to avoid attempting a shrink.
  Otherwise, choose the larger size.
*/ -}}
{{- if or (eq $existingBytes 0.0) (eq $desiredBytes 0.0) -}}
{{- $existingQty -}}
{{- else if gt $existingBytes $desiredBytes -}}
{{- $existingQty -}}
{{- else -}}
{{- $desired -}}
{{- end -}}
{{- end -}}
{{- end -}}
{{- end -}}
