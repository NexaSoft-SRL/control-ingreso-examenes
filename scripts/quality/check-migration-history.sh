#!/usr/bin/env bash

set -euo pipefail

BASE="${MIGRATION_GUARD_BASE:-${1:-origin/main}}"

if ! git rev-parse --verify "${BASE}^{commit}" >/dev/null 2>&1; then
    echo "MIGRATION HISTORY GUARD: ERROR"
    echo "No se pudo resolver la revisión base: ${BASE}"
    exit 2
fi

violations=()

is_historical_migration() {
    local path="$1"

    git cat-file -e "${BASE}:${path}" 2>/dev/null
}

inspect_diff() {
    local mode="$1"

    while IFS=$'\t' read -r status path1 path2; do
        if [[ -z "${status:-}" ]]; then
            continue
        fi

        case "${status}" in
            A)
                # Una migración nueva es válida.
                ;;

            M|D|T)
                if is_historical_migration "${path1}"; then
                    violations+=(
                        "${mode}: ${status} ${path1}"
                    )
                fi
                ;;

            R*|C*)
                if is_historical_migration "${path1}"; then
                    violations+=(
                        "${mode}: ${status} ${path1} -> ${path2}"
                    )
                fi
                ;;

            *)
                if is_historical_migration "${path1}"; then
                    violations+=(
                        "${mode}: ${status} ${path1}"
                    )
                fi
                ;;
        esac
    done
}

inspect_diff "branch" < <(
    git diff \
        --name-status \
        --find-renames \
        "${BASE}...HEAD" \
        -- database/migrations
)

inspect_diff "working-tree" < <(
    git diff \
        --name-status \
        --find-renames \
        HEAD \
        -- database/migrations
)

if (( ${#violations[@]} > 0 )); then
    echo "MIGRATION HISTORY GUARD: FAILED"
    echo
    echo "Las migraciones publicadas son inmutables."
    echo "Cree una nueva migración para modificar el esquema."
    echo

    printf '  %s\n' "${violations[@]}"

    exit 1
fi

echo "MIGRATION HISTORY GUARD: OK"
echo "Las migraciones históricas no fueron modificadas."
