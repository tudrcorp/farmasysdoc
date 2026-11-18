<?php

namespace App\Support\WhatsappAgent;

/**
 * Proveedor de WhatsApp del asistente de pedidos. Hoy Ultramsg; al migrar a la API oficial
 * de Meta solo se agrega otra implementación y se cambia el enlace en el contenedor.
 */
interface WhatsappGateway
{
    public function isConfigured(): bool;

    public function sendText(string $phone, string $body): WhatsappSendResult;

    /**
     * @param  string  $contents  Contenido binario de la imagen.
     */
    public function sendImage(string $phone, string $contents, ?string $caption = null): WhatsappSendResult;

    /**
     * @param  string  $contents  Contenido binario del archivo.
     */
    public function sendDocument(string $phone, string $contents, string $filename, ?string $caption = null): WhatsappSendResult;

    /**
     * Marca el chat como leído (doble check azul) para que el cliente vea que ya lo atienden.
     */
    public function markChatRead(string $phone): bool;

    /**
     * Descarga un archivo (foto o nota de voz) que envió el cliente.
     *
     * @return array{contents: string, mime: string|null}|null
     */
    public function downloadMedia(string $url): ?array;
}
