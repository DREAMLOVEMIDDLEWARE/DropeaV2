FROM php:8.2-cli

WORKDIR /app
COPY . .

CMD ["php", "actualizarTodosProductos.php"]
