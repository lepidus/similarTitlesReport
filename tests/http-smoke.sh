#!/usr/bin/env bash

set -euo pipefail

base_url="${1:-http://127.0.0.1:8000}"
temporary_dir=$(mktemp -d)
manager_cookie="$temporary_dir/manager.cookies"
reader_cookie="$temporary_dir/reader.cookies"
response_body="$temporary_dir/response.html"
response_headers="$temporary_dir/response.headers"

cleanup() {
	rm -f "$manager_cookie" "$reader_cookie" "$response_body" "$response_headers"
	rmdir "$temporary_dir"
}
trap cleanup EXIT

wait_for_ojs() {
	for _attempt in $(seq 1 50); do
		if curl --silent --output /dev/null --max-time 2 "$base_url/index.php/publicknowledge/en/login"; then
			return 0
		fi
		sleep 0.2
	done

	echo "O OJS não ficou disponível para o smoke HTTP." >&2
	return 1
}

login() {
	local username=$1
	local cookie_file=$2
	local csrf_token
	local status

	curl --silent --show-error \
		--cookie-jar "$cookie_file" \
		--output "$response_body" \
		"$base_url/index.php/publicknowledge/en/login"
	csrf_token=$(sed -n 's/.*name="csrfToken" value="\([^"]*\)".*/\1/p' "$response_body" | head -n 1)
	if [[ -z "$csrf_token" ]]; then
		echo "A página de login não forneceu um token CSRF para $username." >&2
		return 1
	fi

	status=$(curl --silent --show-error \
		--cookie "$cookie_file" \
		--cookie-jar "$cookie_file" \
		--output /dev/null \
		--write-out '%{http_code}' \
		--data-urlencode "csrfToken=$csrf_token" \
		--data-urlencode "username=$username" \
		--data-urlencode "password=$username$username" \
		"$base_url/index.php/publicknowledge/en/login/signIn")

	if [[ "$status" != "302" ]]; then
		echo "Login de $username retornou HTTP $status; esperado: 302." >&2
		return 1
	fi
}

wait_for_ojs
login dbarnes "$manager_cookie"

manager_status=$(curl --silent --show-error \
	--cookie "$manager_cookie" \
	--output "$response_body" \
	--write-out '%{http_code}' \
	"$base_url/index.php/publicknowledge/en/similarTitlesReport")

if [[ "$manager_status" != "200" ]]; then
	echo "Relatório para gerente retornou HTTP $manager_status; esperado: 200." >&2
	exit 1
fi
if ! grep -Fq 'id="similarTitlesReport"' "$response_body"; then
	echo "A resposta do gerente não contém o marcador específico do relatório." >&2
	exit 1
fi
if ! grep -Fq 'Submissions with similar titles' "$response_body"; then
	echo "A resposta do gerente não contém o título traduzido do relatório." >&2
	exit 1
fi

login phudson "$reader_cookie"

reader_status=$(curl --silent --show-error \
	--cookie "$reader_cookie" \
	--dump-header "$response_headers" \
	--output "$response_body" \
	--write-out '%{http_code}' \
	"$base_url/index.php/publicknowledge/en/similarTitlesReport")

if [[ "$reader_status" != "302" ]]; then
	echo "Relatório para usuário sem papel editorial retornou HTTP $reader_status; esperado: 302." >&2
	exit 1
fi
if ! grep -Eiq '^location: .*/publicknowledge/en/user/authorizationDenied([?[:space:]]|$)' "$response_headers"; then
	echo "O usuário sem papel editorial não foi redirecionado para authorizationDenied." >&2
	exit 1
fi
if grep -Fq 'id="similarTitlesReport"' "$response_body"; then
	echo "A resposta negada expôs o conteúdo do relatório." >&2
	exit 1
fi

echo "Smoke HTTP aprovado: carregamento real, HTML específico e autorização do relatório."
