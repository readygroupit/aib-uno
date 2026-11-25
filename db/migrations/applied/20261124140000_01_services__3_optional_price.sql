-- Il prezzo del servizio diventa indicativo e facoltativo: il prezzo vero si decide nel preventivo.
ALTER TABLE `services` MODIFY `price` DECIMAL(10,2) NULL;
