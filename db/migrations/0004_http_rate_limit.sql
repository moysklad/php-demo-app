-- очередь запросов JSON API, общая для PHP workers, см. src/php/lib/rate-limit.php
create table http_rate_limit (
    gate_key text primary key,
    not_before double precision not null,
    spacing_ms double precision not null,
    last_used_at bigint not null
);
