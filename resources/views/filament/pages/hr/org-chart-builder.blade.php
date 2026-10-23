<x-filament-panels::page>
    <style>
        .fi-main:has(.fi-org-chart-page) {
            padding: 0 !important;
            max-width: none !important;
        }

        .fi-org-chart-page .fi-page-content {
            padding: 0;
            gap: 0;
        }

        .org-app.is-welcome {
            grid-template-columns: 1fr;
            place-items: center;
            padding: 24px;
        }

        .org-app {
            position: relative;
            height: calc(100dvh - 4rem);
            min-height: 640px;
            display: grid;
            grid-template-columns: 252px minmax(0, 1fr) 320px;
            background: #eef2f4;
            color: #14181d;
        }

        html.dark .org-app {
            background: #07090f;
            color: #f5f7f8;
        }

        .org-side,
        .org-inspector {
            min-height: 0;
            overflow: auto;
        }

        .org-side {
            min-width: 0;
            overflow-x: hidden;
            border-right: 1px solid rgb(15 23 42 / 0.08);
            background: rgb(255 255 255 / 0.72);
            backdrop-filter: blur(16px);
        }

        .org-create {
            display: flex;
            gap: 8px;
            align-items: center;
            min-width: 0;
        }

        .org-create .org-field {
            flex: 1 1 auto;
            width: auto;
            min-width: 0;
            max-width: 100%;
        }

        .org-create .org-btn {
            flex: 0 0 auto;
            white-space: nowrap;
        }

        .org-side .org-field {
            min-width: 0;
            max-width: 100%;
        }

        .org-side .org-btn {
            flex: 0 0 auto;
            white-space: nowrap;
        }

        html.dark .org-side {
            border-right-color: rgb(255 255 255 / 0.06);
            background: rgb(12 16 24 / 0.88);
        }

        .org-inspector {
            border-left: 1px solid rgb(15 23 42 / 0.08);
            background: rgb(255 255 255 / 0.9);
        }

        html.dark .org-inspector {
            border-left-color: rgb(255 255 255 / 0.06);
            background: #0d121a;
        }

        .org-viewport {
            position: relative;
            min-width: 0;
            min-height: 0;
            overflow: hidden;
            background-color: #e7eef1;
            background-image: radial-gradient(circle, rgb(14 148 154 / 0.28) 1px, transparent 1px);
            background-size: 22px 22px;
            cursor: grab;
            touch-action: none;
        }

        html.dark .org-viewport {
            background-color: #090d14;
            background-image: radial-gradient(circle, rgb(24 172 178 / 0.22) 1px, transparent 1px);
        }

        .org-viewport.is-panning {
            cursor: grabbing;
        }

        .org-world {
            position: absolute;
            top: 0;
            left: 0;
            transform-origin: 0 0;
        }

        .org-edges {
            position: absolute;
            inset: 0;
            overflow: visible;
            pointer-events: none;
        }

        .org-edge {
            fill: none;
            stroke: #0e949a;
            stroke-width: 2.4;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        html.dark .org-edge {
            stroke: #5ee0e6;
        }

        .org-edge.is-draft {
            stroke-dasharray: 5 6;
            opacity: 0.8;
        }

        .org-node {
            position: absolute;
            width: 220px;
            height: 96px;
            padding: 10px 12px 16px;
            border-radius: 18px;
            border: 1px solid rgb(15 23 42 / 0.08);
            background: white;
            text-align: left;
            box-shadow: 0 10px 30px rgb(15 23 42 / 0.06);
            transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
        }

        html.dark .org-node {
            border-color: rgb(255 255 255 / 0.08);
            background: #141b26;
            box-shadow: 0 16px 40px rgb(0 0 0 / 0.35);
        }

        .org-node:hover {
            transform: translateY(-1px);
        }

        .org-node.is-selected,
        .org-node.is-link-target {
            border-color: #0e949a;
            box-shadow: 0 0 0 4px rgb(14 148 154 / 0.16), 0 14px 34px rgb(14 148 154 / 0.12);
        }

        .org-node.is-vacant {
            border-style: dashed;
        }

        .org-node.is-match {
            border-color: #f5c518;
        }

        .org-node.is-person {
            width: 196px;
            border-radius: 999px;
            padding-right: 18px;
        }

        .org-node.is-unit {
            width: 248px;
            border-radius: 12px;
            border-left: 4px solid #0e949a;
        }

        .org-palette {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
        }

        .org-palette-hint {
            margin: 0;
            font-size: 12px;
            line-height: 1.4;
            opacity: 0.65;
        }

        .org-palette-row {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .org-piece {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            min-width: 0;
            padding: 8px 10px;
            border-radius: 12px;
            border: 1px dashed rgb(15 23 42 / 0.18);
            background: white;
            color: inherit;
            font: inherit;
            font-size: 13px;
            font-weight: 650;
            text-align: left;
            cursor: grab;
        }

        html.dark .org-piece {
            background: #141b26;
            border-color: rgb(255 255 255 / 0.14);
        }

        .org-piece:active {
            cursor: grabbing;
        }

        .org-piece-glyph {
            width: 28px;
            height: 16px;
            flex: none;
            border: 1.5px solid #0e949a;
            background: rgb(14 148 154 / 0.12);
        }

        .org-piece.is-card .org-piece-glyph {
            border-radius: 4px;
        }

        .org-piece.is-person .org-piece-glyph {
            width: 16px;
            height: 16px;
            margin-left: 6px;
            border-radius: 999px;
        }

        .org-piece.is-unit .org-piece-glyph {
            border-radius: 2px;
            border-left-width: 4px;
        }

        .org-viewport.is-shape-target {
            box-shadow: inset 0 0 0 2px #0e949a;
        }

        .org-handle {
            position: absolute;
            z-index: 4;
            width: 8px;
            height: 8px;
            margin: 0;
            padding: 0;
            border-radius: 999px;
            border: 1.5px solid white;
            background: #0e949a;
            cursor: crosshair;
            touch-action: none;
        }

        .org-handle::before {
            content: '';
            position: absolute;
            inset: -7px;
        }

        .org-handle.is-top {
            top: -4px;
            left: 50%;
            margin-left: -4px;
        }

        .org-handle.is-right {
            top: 50%;
            right: -4px;
            margin-top: -4px;
        }

        .org-handle.is-bottom {
            bottom: -4px;
            left: 50%;
            margin-left: -4px;
        }

        .org-handle.is-left {
            top: 50%;
            left: -4px;
            margin-top: -4px;
        }

        html.dark .org-handle {
            border-color: #141b26;
        }

        .org-toolbar,
        .org-dock,
        .org-minimap,
        .org-empty {
            position: absolute;
            z-index: 5;
        }

        .org-toolbar {
            top: 14px;
            left: 14px;
            right: 14px;
            display: flex;
            gap: 8px;
            align-items: center;
            pointer-events: none;
        }

        .org-toolbar > * {
            pointer-events: auto;
        }

        .org-chip,
        .org-field,
        .org-btn {
            border-radius: 999px;
            border: 1px solid rgb(15 23 42 / 0.08);
            background: rgb(255 255 255 / 0.92);
            color: inherit;
            font: inherit;
        }

        html.dark .org-chip,
        html.dark .org-field,
        html.dark .org-btn {
            border-color: rgb(255 255 255 / 0.08);
            background: rgb(16 22 32 / 0.92);
            color: inherit;
        }

        .org-btn {
            height: 36px;
            padding: 0 12px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .org-btn.is-accent {
            background: #fce422;
            color: #1a1603;
            border-color: transparent;
        }

        .org-btn:disabled {
            opacity: 0.55;
            cursor: progress;
        }

        .org-field {
            height: 36px;
            padding: 0 12px;
            font-size: 13px;
            outline: none;
            width: min(280px, 36vw);
        }

        .org-field:focus,
        .org-area:focus,
        .org-select:focus {
            border-color: #0e949a;
            box-shadow: 0 0 0 3px rgb(14 148 154 / 0.16);
        }

        .org-dock {
            left: 50%;
            bottom: 16px;
            width: min(560px, calc(100% - 32px));
            transform: translateX(-50%);
            border-radius: 22px;
            border: 1px solid rgb(15 23 42 / 0.08);
            background: rgb(255 255 255 / 0.94);
            box-shadow: 0 18px 50px rgb(15 23 42 / 0.12);
            padding: 12px;
        }

        html.dark .org-dock {
            border-color: rgb(255 255 255 / 0.08);
            background: rgb(13 18 27 / 0.94);
            box-shadow: 0 18px 50px rgb(0 0 0 / 0.4);
        }

        .org-area {
            width: 100%;
            resize: vertical;
            min-height: 112px;
            border-radius: 16px;
            border: 1px solid rgb(15 23 42 / 0.08);
            background: transparent;
            color: inherit;
            font: inherit;
            font-size: 13px;
            line-height: 1.45;
            padding: 10px 12px;
            outline: none;
        }

        html.dark .org-area,
        html.dark .org-select {
            border-color: rgb(255 255 255 / 0.08);
        }

        .org-minimap {
            top: 62px;
            right: 14px;
            width: 148px;
            height: 92px;
            border-radius: 14px;
            border: 1px solid rgb(15 23 42 / 0.08);
            background: rgb(255 255 255 / 0.82);
            overflow: hidden;
            cursor: pointer;
        }

        html.dark .org-minimap {
            border-color: rgb(255 255 255 / 0.08);
            background: rgb(16 22 32 / 0.82);
        }

        .org-empty {
            inset: 0;
            display: grid;
            place-items: center;
            pointer-events: none;
        }

        .org-empty-card {
            pointer-events: auto;
            width: min(420px, calc(100% - 48px));
            border-radius: 28px;
            padding: 28px;
            background: rgb(255 255 255 / 0.94);
            border: 1px solid rgb(15 23 42 / 0.06);
            box-shadow: 0 24px 60px rgb(15 23 42 / 0.1);
        }

        html.dark .org-empty-card {
            background: rgb(16 22 32 / 0.94);
            border-color: rgb(255 255 255 / 0.06);
        }

        .org-select,
        .org-inspector input[type="text"],
        .org-inspector input[type="search"] {
            width: 100%;
            border-radius: 12px;
            border: 1px solid rgb(15 23 42 / 0.1);
            background: transparent;
            color: inherit;
            font: inherit;
            font-size: 14px;
            padding: 10px 12px;
            outline: none;
        }

        .org-kicker {
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            opacity: 0.55;
        }

        @media (max-width: 1100px) {
            .org-app {
                grid-template-columns: 220px minmax(0, 1fr);
            }

            .org-inspector {
                position: absolute;
                right: 12px;
                top: 64px;
                bottom: 12px;
                width: min(320px, calc(100% - 24px));
                z-index: 6;
                border-radius: 20px;
                box-shadow: 0 18px 50px rgb(15 23 42 / 0.18);
            }
        }
    </style>

    @if ($this->chartId === null)
        <div class="org-app is-welcome">
            <div class="org-empty-card">
                <p class="org-kicker">Recursos humanos</p>
                <h2 style="margin: 8px 0 6px; font-size: 28px; line-height: 1.15; font-weight: 650;">El organigrama de la empresa, en un lienzo.</h2>
                <p style="margin: 0 0 18px; opacity: 0.72; font-size: 14px; line-height: 1.5;">Crea cargos, conéctalos como quieras y asigna personas, sucursales o déjalos vacantes. Sirve para una pirámide, una cadena de farmacias o cualquier otra estructura.</p>
                <form class="org-create" wire:submit="createChart">
                    <input class="org-field" style="border-radius: 14px;" wire:model="newChartName" placeholder="Nombre, por ejemplo Corporativo" />
                    <button class="org-btn is-accent" type="submit">Crear</button>
                </form>
            </div>
        </div>
    @else
        <div class="org-app">
            <aside class="org-side" style="padding: 16px; display: flex; flex-direction: column; gap: 14px;">
                <div>
                    <p class="org-kicker">Recursos humanos</p>
                    <strong style="display: block; margin-top: 4px; font-size: 16px;">Organigramas</strong>
                </div>
                <form class="org-create" wire:submit="createChart">
                    <input class="org-field" style="border-radius: 12px;" wire:model="newChartName" placeholder="Nuevo" />
                    <button class="org-btn is-accent" type="submit">Crear</button>
                </form>
                <div
                    class="org-palette"
                    x-data="{ dragged: false }"
                >
                    <p class="org-kicker">Figuras</p>
                    <p class="org-palette-hint">Arrástralas al lienzo. Los puntos de cada lado se sueltan sobre otra figura para conectarlas.</p>
                    <div class="org-palette-row">
                        <button type="button" class="org-piece is-card" draggable="true" x-on:dragstart="dragged = true; $event.dataTransfer.setData('text/plain', 'shape:card'); $event.dataTransfer.effectAllowed = 'copy'" x-on:dragend="setTimeout(() => dragged = false, 0)" x-on:click="if (!dragged) window.dispatchEvent(new CustomEvent('org-add-shape', { detail: { shape: 'card' } }))">
                            <span class="org-piece-glyph"></span>
                            Cuadro
                        </button>
                        <button type="button" class="org-piece is-person" draggable="true" x-on:dragstart="dragged = true; $event.dataTransfer.setData('text/plain', 'shape:person'); $event.dataTransfer.effectAllowed = 'copy'" x-on:dragend="setTimeout(() => dragged = false, 0)" x-on:click="if (!dragged) window.dispatchEvent(new CustomEvent('org-add-shape', { detail: { shape: 'person' } }))">
                            <span class="org-piece-glyph"></span>
                            Persona
                        </button>
                        <button type="button" class="org-piece is-unit" draggable="true" x-on:dragstart="dragged = true; $event.dataTransfer.setData('text/plain', 'shape:unit'); $event.dataTransfer.effectAllowed = 'copy'" x-on:dragend="setTimeout(() => dragged = false, 0)" x-on:click="if (!dragged) window.dispatchEvent(new CustomEvent('org-add-shape', { detail: { shape: 'unit' } }))">
                            <span class="org-piece-glyph"></span>
                            Unidad
                        </button>
                    </div>
                </div>
                <div style="display: flex; flex-direction: column; gap: 6px;">
                    @foreach ($this->charts as $chart)
                        <button
                            type="button"
                            wire:key="chart-{{ $chart->id }}"
                            wire:click="openChart({{ $chart->id }})"
                            style="text-align: left; border-radius: 14px; padding: 10px 12px; border: 1px solid {{ $chart->id === $this->chartId ? '#0e949a' : 'transparent' }}; background: {{ $chart->id === $this->chartId ? 'rgb(14 148 154 / 0.1)' : 'transparent' }}; color: inherit; font: inherit; cursor: pointer;"
                        >
                            <span style="display: block; font-weight: 650; font-size: 14px;">{{ $chart->name }}</span>
                            <span style="display: block; font-size: 12px; opacity: 0.6;">{{ $chart->nodes_count }} {{ $chart->nodes_count === 1 ? 'cargo' : 'cargos' }}</span>
                        </button>
                    @endforeach
                </div>
                <div style="margin-top: auto; display: flex; flex-direction: column; gap: 8px;">
                    <label class="org-kicker" for="org-chart-name">Nombre</label>
                    <input id="org-chart-name" class="org-field" style="width: 100%; border-radius: 12px;" wire:model.blur="chartName" />
                    <div class="org-create">
                        <button class="org-btn" type="button" style="flex: 1;" wire:click="duplicateChart">Duplicar</button>
                        <button class="org-btn" type="button" style="flex: 1;" wire:click="deleteChart" wire:confirm="¿Eliminar este organigrama y todos sus cargos?">Eliminar</button>
                    </div>
                </div>
            </aside>

            <div
                class="org-viewport"
                wire:ignore
                wire:key="studio-{{ $this->chartId }}"
                x-data="orgStudio"
                x-init="boot(JSON.parse($el.querySelector('[data-board]').textContent))"
                x-on:org-synced.window="apply($event.detail)"
                x-on:pointermove.window="move($event)"
                x-on:pointerup.window="up($event)"
                x-on:keydown.window="onKey($event)"
                x-bind:class="{ 'is-panning': panning, 'is-shape-target': shapeOver }"
                x-bind:style="`background-position: ${panX}px ${panY}px`"
                x-on:pointerdown="startPan($event)"
                x-on:wheel.prevent="zoom($event)"
                x-on:dblclick="addAt($event)"
                x-on:dragover.prevent="shapeOver = true"
                x-on:drop.prevent="dropShape($event)"
                x-ref="viewport"
            >
                <script type="application/json" data-board>@json($this->boardPayload())</script>
                <div class="org-toolbar">
                    <input class="org-field" type="search" placeholder="Buscar cargo, persona o sucursal" x-model="search" x-on:keydown.enter.prevent="focusMatch()" />
                    <span class="org-chip" style="height: 36px; display: inline-flex; align-items: center; padding: 0 12px; font-size: 12px;" x-text="summary()"></span>
                    <span style="flex: 1;"></span>
                    <button class="org-btn" type="button" x-on:click="nudgeZoom(-1)">−</button>
                    <button class="org-btn" type="button" x-on:click="scale = 1" x-text="Math.round(scale * 100) + '%'"></button>
                    <button class="org-btn" type="button" x-on:click="nudgeZoom(1)">+</button>
                    <button class="org-btn" type="button" x-on:click="fit()">Encajar</button>
                    <button class="org-btn" type="button" x-bind:disabled="busy" x-on:click="run('arrange')">Ordenar</button>
                    <button class="org-btn" type="button" x-bind:disabled="busy" x-on:click="run('undo')">Deshacer</button>
                    <button class="org-btn is-accent" type="button" x-on:click="addCentered()">Añadir cargo</button>
                </div>

                <div class="org-minimap" x-show="nodes.length" x-on:pointerdown.stop="minimapPan($event)">
                    <svg width="148" height="92">
                        <template x-for="node in nodes" :key="'map-' + node.id">
                            <rect
                                :x="mapX(node)"
                                :y="mapY(node)"
                                width="10"
                                height="6"
                                rx="1.5"
                                :fill="selectedId === node.id ? '#0e949a' : 'currentColor'"
                                opacity="0.75"
                            ></rect>
                        </template>
                    </svg>
                </div>

                <div class="org-world" x-bind:style="worldStyle()">
                    <svg class="org-edges" :width="worldSize().w" :height="worldSize().h">
                        <path class="org-edge" x-bind:d="edgePath() || 'M 0 0'" x-bind:visibility="edgePath() ? 'visible' : 'hidden'"></path>
                        <path class="org-edge is-draft" x-show="linking" x-bind:d="draftPath()"></path>
                    </svg>
                    <template x-for="node in nodes" :key="node.id">
                        <article
                            class="org-node"
                            x-bind:data-node-id="node.id"
                            x-bind:class="{
                                'is-selected': selectedId === node.id,
                                'is-vacant': node.vacant,
                                'is-match': isMatch(node),
                                'is-person': node.shape === 'person',
                                'is-unit': node.shape === 'unit',
                                'is-link-target': linkHoverId === node.id,
                            }"
                            x-bind:style="`left:${node.x}px;top:${node.y}px;z-index:${selectedId === node.id ? 3 : 2}`"
                            x-on:pointerdown.stop="startDrag($event, node)"
                        >
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <template x-if="node.photo">
                                    <img x-bind:src="node.photo" alt="" style="width: 32px; height: 32px; border-radius: 999px; object-fit: cover; flex: none;" />
                                </template>
                                <template x-if="!node.photo">
                                    <span style="width: 32px; height: 32px; border-radius: 999px; display: grid; place-items: center; background: rgb(14 148 154 / 0.14); color: #0e949a; font-size: 11px; font-weight: 700; flex: none;" x-text="node.initials"></span>
                                </template>
                                <span style="min-width: 0;">
                                    <span style="display: block; font-size: 13px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="node.title"></span>
                                    <span style="display: block; font-size: 12px; opacity: 0.62; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="node.person || 'Vacante'"></span>
                                </span>
                            </div>
                            <div style="display: flex; gap: 6px; margin-top: 8px; min-width: 0;">
                                <span x-show="node.branch" style="font-size: 10px; padding: 2px 7px; border-radius: 999px; background: rgb(14 148 154 / 0.12); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="node.branch"></span>
                                <span x-show="node.area" style="font-size: 10px; padding: 2px 7px; border-radius: 999px; background: rgb(15 23 42 / 0.06); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="node.area"></span>
                            </div>
                            <template x-for="side in ['top', 'right', 'bottom', 'left']" :key="node.id + '-' + side">
                                <button class="org-handle" x-bind:class="'is-' + side" type="button" draggable="false" title="Arrastra este punto hasta otra figura" x-on:pointerdown.stop.prevent="startLink($event, node, side)"></button>
                            </template>
                        </article>
                    </template>
                </div>

                <div class="org-empty" x-show="nodes.length === 0" x-cloak>
                    <div class="org-empty-card">
                        <p class="org-kicker">Lienzo vacío</p>
                        <h2 style="margin: 8px 0; font-size: 22px; font-weight: 650;">Arma la estructura desde aquí.</h2>
                        <p style="margin: 0 0 14px; font-size: 14px; line-height: 1.5; opacity: 0.7;">Arrastra un cuadro, una persona o una unidad desde la izquierda. Luego conecta cualquiera de los puntos con otra figura.</p>
                        <button class="org-btn is-accent" type="button" x-on:click="addCentered()">Añadir el primer cargo</button>
                    </div>
                </div>

                <div class="org-dock">
                    <button type="button" x-show="!assistant" x-on:click="assistant = true" style="width: 100%; text-align: left; background: transparent; border: 0; color: inherit; font: inherit; cursor: pointer; padding: 4px 2px;">
                        <span class="org-kicker">Asistente</span>
                        <span style="display: block; margin-top: 2px; font-size: 14px;">Describe cualquier organigrama, o pégalo con sangría.</span>
                    </button>
                    <div x-show="assistant" x-cloak>
                        <div style="display: flex; justify-content: space-between; gap: 8px; align-items: center; margin-bottom: 8px;">
                            <span class="org-kicker">Asistente</span>
                            <button class="org-btn" type="button" x-on:click="assistant = false">Cerrar</button>
                        </div>
                        <textarea class="org-area" x-model="prompt" placeholder="Gerencia general&#10;  Operaciones&#10;    Regente Chacao — María León&#10;      Caja&#10;  Recursos humanos&#10;&#10;También vale: Gerencia > Operaciones > Caja&#10;O una línea: escribe sucursales"></textarea>
                        <div style="display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap;">
                            <button class="org-btn is-accent" type="button" x-bind:disabled="busy" x-on:click="generate()">Generar</button>
                            <button class="org-btn" type="button" x-bind:disabled="busy" x-on:click="seed()">Desde sucursales</button>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="org-inspector" style="padding: 18px; display: flex; flex-direction: column; gap: 14px;">
                @if ($this->nodeId === null)
                    <p class="org-kicker">Ficha</p>
                    <strong style="font-size: 18px;">Así se construye</strong>
                    <ol style="margin: 0; padding-left: 18px; display: flex; flex-direction: column; gap: 8px; font-size: 14px; line-height: 1.45; opacity: 0.78;">
                        <li>Arrastra un cuadro, una persona o una unidad al lienzo.</li>
                        <li>Conecta un punto de cualquier lado con otra figura.</li>
                        <li>Asigna persona, sucursal y unidad. Si no hay persona, queda vacante.</li>
                        <li>Ordenar acomoda el dibujo. Los enlaces se mantienen.</li>
                    </ol>
                @else
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <p class="org-kicker">Ficha del cargo</p>
                        <button class="org-btn" type="button" wire:click="deleteNode({{ $this->nodeId }})" wire:confirm="¿Quitar este cargo? Quienes le reportan pasan al nivel de arriba.">Quitar</button>
                    </div>
                    <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px;">
                        Cargo
                        <input type="text" wire:model.blur="nodeTitle" />
                    </label>
                    <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px;">
                        Figura
                        <select class="org-select" wire:model.live="nodeShape">
                            <option value="card">Cuadro</option>
                            <option value="person">Persona</option>
                            <option value="unit">Unidad</option>
                        </select>
                    </label>
                    <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px;">
                        Unidad
                        <input type="text" wire:model.blur="nodeArea" placeholder="Gerencia, caja, depósito…" />
                    </label>
                    <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px;">
                        Reporta a
                        <select class="org-select" wire:model.live="nodeParentId">
                            <option value="">Nadie · queda en la raíz</option>
                            @foreach ($this->parentChoices as $parent)
                                <option value="{{ $parent->id }}">{{ $parent->title }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px;">
                        Sucursal
                        <select class="org-select" wire:model.live="nodeBranchId">
                            <option value="">Sin sucursal</option>
                            @foreach ($this->branchChoices as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px;">
                        Persona
                        <input type="search" wire:model.live.debounce.250ms="employeeQuery" placeholder="Buscar empleado" />
                        <select class="org-select" wire:model.live="nodeEmployeeId">
                            <option value="">Vacante</option>
                            @foreach ($this->employeeChoices as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->fullName() }} · {{ $employee->national_id }}</option>
                            @endforeach
                        </select>
                    </label>
                    <p style="margin: 0; font-size: 12px; opacity: 0.6; line-height: 1.4;">La misma persona puede ocupar más de un cargo. Un cargo sin persona se muestra como vacante.</p>
                @endif
            </aside>
        </div>
    @endif

    @script
    <script>
        if (! window.__orgStudioRegistered) {
            window.__orgStudioRegistered = true;
            Alpine.data('orgStudio', () => ({
                nodes: [],
                chartId: null,
                scale: 1,
                panX: 48,
                panY: 72,
                search: '',
                prompt: '',
                assistant: false,
                busy: false,
                selectedId: null,
                drag: null,
                panning: null,
                linking: null,
                linkCursor: null,
                linkSide: 'bottom',
                linkOrigin: null,
                linkClient: null,
                linkHoverId: null,
                shapeOver: false,
                boot(payload) {
                    this.apply(payload);
                    window.__orgStudioAdd = (shape) => this.addShape(shape);
                    if (!window.__orgShapeListener) {
                        window.__orgShapeListener = true;
                        window.addEventListener('org-add-shape', (event) => {
                            window.__orgStudioAdd?.(event.detail?.shape || 'card');
                        });
                    }
                    this.$nextTick(() => this.fit());
                },
                apply(detail) {
                    const payload = detail && detail.payload ? detail.payload : detail;
                    if (!payload || !Array.isArray(payload.nodes)) {
                        return;
                    }
                    this.chartId = payload.chartId;
                    this.nodes = payload.nodes.map((node) => ({ ...node }));
                    if (this.selectedId && !this.nodes.some((node) => node.id === this.selectedId)) {
                        this.selectedId = null;
                    }
                },
                summary() {
                    const vacant = this.nodes.filter((node) => node.vacant).length;
                    const noun = this.nodes.length === 1 ? 'cargo' : 'cargos';
                    return this.nodes.length + ' ' + noun + (vacant ? ' · ' + vacant + ' vacantes' : '');
                },
                nodeById(id) {
                    return this.nodes.find((node) => node.id === id);
                },
                box(node) {
                    if (node?.shape === 'person') {
                        return { w: 196, h: 92 };
                    }
                    if (node?.shape === 'unit') {
                        return { w: 248, h: 92 };
                    }
                    return { w: 220, h: 96 };
                },
                worldPoint(event) {
                    const rect = this.$refs.viewport.getBoundingClientRect();
                    return {
                        x: (event.clientX - rect.left - this.panX) / this.scale,
                        y: (event.clientY - rect.top - this.panY) / this.scale,
                    };
                },
                worldStyle() {
                    const size = this.worldSize();
                    return `width:${size.w}px;height:${size.h}px;transform:translate(${this.panX}px, ${this.panY}px) scale(${this.scale})`;
                },
                worldSize() {
                    const bounds = this.bounds();
                    return {
                        w: Math.max(1600, bounds.maxX + 480),
                        h: Math.max(900, bounds.maxY + 360),
                    };
                },
                bounds() {
                    if (!this.nodes.length) {
                        return { minX: 0, minY: 0, maxX: 800, maxY: 500, width: 800, height: 500 };
                    }
                    const minX = Math.min(...this.nodes.map((node) => node.x));
                    const minY = Math.min(...this.nodes.map((node) => node.y));
                    const maxX = Math.max(...this.nodes.map((node) => node.x + this.box(node).w));
                    const maxY = Math.max(...this.nodes.map((node) => node.y + this.box(node).h));
                    return { minX, minY, maxX, maxY, width: Math.max(220, maxX - minX), height: Math.max(96, maxY - minY) };
                },
                edges() {
                    return this.nodes
                        .filter((node) => node.parentId)
                        .map((node) => {
                            const parent = this.nodeById(node.parentId);
                            if (!parent) {
                                return null;
                            }
                            return { key: parent.id + '-' + node.id, d: this.curve(parent, node) };
                        })
                        .filter(Boolean);
                },
                edgePath() {
                    return this.edges().map((edge) => edge.d).join(' ');
                },
                wouldCycle(childId, parentId) {
                    let current = parentId;
                    const seen = new Set();
                    while (current) {
                        if (current === childId || seen.has(current)) {
                            return true;
                        }
                        seen.add(current);
                        const next = this.nodeById(current);
                        current = next ? next.parentId : null;
                    }
                    return false;
                },
                nodeAtClient(clientX, clientY, ignoreId = null) {
                    const point = this.worldPoint({ clientX, clientY });
                    let best = null;
                    let bestArea = Infinity;
                    this.nodes.forEach((node) => {
                        if (ignoreId !== null && node.id === ignoreId) {
                            return;
                        }
                        const size = this.box(node);
                        const pad = 28;
                        const inside = point.x >= node.x - pad
                            && point.x <= node.x + size.w + pad
                            && point.y >= node.y - pad
                            && point.y <= node.y + size.h + pad;
                        if (!inside) {
                            return;
                        }
                        const area = size.w * size.h;
                        if (area < bestArea) {
                            best = node;
                            bestArea = area;
                        }
                    });
                    return best;
                },
                port(node, side) {
                    const size = this.box(node);
                    const midX = node.x + size.w / 2;
                    const midY = node.y + size.h / 2;
                    if (side === 'top') {
                        return { x: midX, y: node.y, nx: 0, ny: -1 };
                    }
                    if (side === 'right') {
                        return { x: node.x + size.w, y: midY, nx: 1, ny: 0 };
                    }
                    if (side === 'left') {
                        return { x: node.x, y: midY, nx: -1, ny: 0 };
                    }
                    return { x: midX, y: node.y + size.h, nx: 0, ny: 1 };
                },
                facing(from, to) {
                    const fromSize = this.box(from);
                    const toSize = this.box(to);
                    const dx = (to.x + toSize.w / 2) - (from.x + fromSize.w / 2);
                    const dy = (to.y + toSize.h / 2) - (from.y + fromSize.h / 2);
                    if (Math.abs(dx) > Math.abs(dy)) {
                        return dx >= 0 ? 'right' : 'left';
                    }
                    return dy >= 0 ? 'bottom' : 'top';
                },
                curve(from, to) {
                    const start = this.port(from, this.facing(from, to));
                    const end = this.port(to, this.facing(to, from));
                    const span = Math.hypot(end.x - start.x, end.y - start.y);
                    const bend = Math.max(28, Math.min(110, span * 0.45));
                    return `M ${start.x} ${start.y} C ${start.x + start.nx * bend} ${start.y + start.ny * bend}, ${end.x + end.nx * bend} ${end.y + end.ny * bend}, ${end.x} ${end.y}`;
                },
                draftPath() {
                    if (!this.linking || !this.linkCursor) {
                        return '';
                    }
                    const start = this.port(this.linking, this.linkSide || 'bottom');
                    const x2 = this.linkCursor.x;
                    const y2 = this.linkCursor.y;
                    const bend = 48;
                    return `M ${start.x} ${start.y} C ${start.x + start.nx * bend} ${start.y + start.ny * bend}, ${x2} ${y2}, ${x2} ${y2}`;
                },
                mapX(node) {
                    const bounds = this.bounds();
                    return ((node.x - bounds.minX) / bounds.width) * 128 + 6;
                },
                mapY(node) {
                    const bounds = this.bounds();
                    return ((node.y - bounds.minY) / bounds.height) * 74 + 6;
                },
                minimapPan(event) {
                    const rect = event.currentTarget.getBoundingClientRect();
                    const bounds = this.bounds();
                    const worldX = bounds.minX + ((event.clientX - rect.left) / rect.width) * bounds.width;
                    const worldY = bounds.minY + ((event.clientY - rect.top) / rect.height) * bounds.height;
                    const view = this.$refs.viewport.getBoundingClientRect();
                    this.panX = view.width / 2 - worldX * this.scale;
                    this.panY = view.height / 2 - worldY * this.scale;
                },
                startPan(event) {
                    if (event.button !== 0) {
                        return;
                    }
                    if (event.target.closest('[data-node-id], .org-dock, .org-toolbar, .org-minimap, .org-empty-card')) {
                        return;
                    }
                    this.panning = { x: event.clientX, y: event.clientY, panX: this.panX, panY: this.panY };
                },
                startDrag(event, node) {
                    if (event.button !== 0 || event.target.closest('.org-handle')) {
                        return;
                    }
                    this.selectedId = node.id;
                    this.$wire.selectNode(node.id);
                    this.drag = { id: node.id, x: node.x, y: node.y, clientX: event.clientX, clientY: event.clientY };
                },
                startLink(event, node, side = 'bottom') {
                    if (event.button !== 0) {
                        return;
                    }
                    this.drag = null;
                    this.panning = null;
                    this.linking = node;
                    this.linkSide = side || 'bottom';
                    this.linkOrigin = { x: event.clientX, y: event.clientY };
                    this.linkClient = { x: event.clientX, y: event.clientY };
                    this.linkCursor = this.worldPoint(event);
                    this.linkHoverId = null;
                },
                move(event) {
                    if (this.panning) {
                        this.panX = this.panning.panX + (event.clientX - this.panning.x);
                        this.panY = this.panning.panY + (event.clientY - this.panning.y);
                    }
                    if (this.drag) {
                        const node = this.nodeById(this.drag.id);
                        if (node) {
                            node.x = this.drag.x + (event.clientX - this.drag.clientX) / this.scale;
                            node.y = this.drag.y + (event.clientY - this.drag.clientY) / this.scale;
                        }
                    }
                    if (this.linking) {
                        this.linkClient = { x: event.clientX, y: event.clientY };
                        this.linkCursor = this.worldPoint(event);
                        const hover = this.nodeAtClient(event.clientX, event.clientY, this.linking.id);
                        this.linkHoverId = hover ? hover.id : null;
                    }
                },
                up(event) {
                    if (this.drag) {
                        const node = this.nodeById(this.drag.id);
                        if (node && (Math.abs(node.x - this.drag.x) > 3 || Math.abs(node.y - this.drag.y) > 3)) {
                            this.$wire.moveNode(node.id, Math.round(node.x), Math.round(node.y));
                        }
                        this.drag = null;
                    }
                    if (this.linking) {
                        const clientX = event.clientX || this.linkClient?.x || 0;
                        const clientY = event.clientY || this.linkClient?.y || 0;
                        const moved = this.linkOrigin
                            ? Math.hypot(clientX - this.linkOrigin.x, clientY - this.linkOrigin.y)
                            : 0;
                        const child = this.nodeAtClient(clientX, clientY, this.linking.id);
                        if (child && moved > 8 && !this.wouldCycle(child.id, this.linking.id)) {
                            child.parentId = this.linking.id;
                            this.$wire.setParent(child.id, this.linking.id);
                        }
                        this.linking = null;
                        this.linkCursor = null;
                        this.linkSide = 'bottom';
                        this.linkOrigin = null;
                        this.linkClient = null;
                        this.linkHoverId = null;
                    }
                    this.panning = null;
                },
                addAt(event) {
                    if (event.target.closest('[data-node-id], .org-dock, .org-toolbar, .org-minimap')) {
                        return;
                    }
                    const point = this.worldPoint(event);
                    this.$wire.addNodeAt(Math.round(point.x - 110), Math.round(point.y - 48), null, 'card');
                },
                addCentered(shape = 'card') {
                    const rect = this.$refs.viewport.getBoundingClientRect();
                    const x = (rect.width / 2 - this.panX) / this.scale - 110;
                    const y = (rect.height / 2 - this.panY) / this.scale - 48;
                    this.$wire.addNodeAt(Math.round(x), Math.round(y), this.selectedId, shape || 'card');
                },
                addShape(shape) {
                    this.addCentered(shape || 'card');
                },
                dropShape(event) {
                    this.shapeOver = false;
                    const raw = event.dataTransfer ? event.dataTransfer.getData('text/plain') : '';
                    if (!raw.startsWith('shape:')) {
                        return;
                    }
                    const shape = raw.slice(6);
                    const point = this.worldPoint(event);
                    const hit = document.elementFromPoint(event.clientX, event.clientY);
                    const card = hit && hit.closest ? hit.closest('[data-node-id]') : null;
                    let parentId = null;
                    let x = Math.round(point.x - 110);
                    let y = Math.round(point.y - 46);
                    if (card) {
                        parentId = Number(card.dataset.nodeId);
                        const parent = this.nodeById(parentId);
                        if (parent) {
                            x = parent.x;
                            y = parent.y + this.box(parent).h + 36;
                        }
                    }
                    this.$wire.addNodeAt(x, y, parentId, shape);
                },
                zoom(event) {
                    const next = Math.min(1.8, Math.max(0.35, this.scale * (event.deltaY < 0 ? 1.08 : 0.92)));
                    const rect = this.$refs.viewport.getBoundingClientRect();
                    const px = event.clientX - rect.left;
                    const py = event.clientY - rect.top;
                    const wx = (px - this.panX) / this.scale;
                    const wy = (py - this.panY) / this.scale;
                    this.scale = next;
                    this.panX = px - wx * next;
                    this.panY = py - wy * next;
                },
                nudgeZoom(direction) {
                    const rect = this.$refs.viewport.getBoundingClientRect();
                    this.zoom({
                        deltaY: direction > 0 ? -1 : 1,
                        clientX: rect.left + rect.width / 2,
                        clientY: rect.top + rect.height / 2,
                    });
                },
                fit() {
                    if (!this.nodes.length || !this.$refs.viewport) {
                        return;
                    }
                    const bounds = this.bounds();
                    const rect = this.$refs.viewport.getBoundingClientRect();
                    const scale = Math.min(1.2, Math.max(0.35, Math.min((rect.width - 80) / bounds.width, (rect.height - 160) / bounds.height)));
                    this.scale = scale;
                    this.panX = (rect.width - bounds.width * scale) / 2 - bounds.minX * scale;
                    this.panY = 88 + ((rect.height - 160 - bounds.height * scale) / 2) - bounds.minY * scale;
                },
                isMatch(node) {
                    const query = this.search.trim().toLowerCase();
                    if (!query) {
                        return false;
                    }
                    return `${node.title} ${node.person || ''} ${node.branch || ''} ${node.area || ''}`.toLowerCase().includes(query);
                },
                focusMatch() {
                    const match = this.nodes.find((node) => this.isMatch(node));
                    if (!match) {
                        return;
                    }
                    this.selectedId = match.id;
                    this.$wire.selectNode(match.id);
                    const rect = this.$refs.viewport.getBoundingClientRect();
                    this.panX = rect.width / 2 - (match.x + 110) * this.scale;
                    this.panY = rect.height / 2 - (match.y + 48) * this.scale;
                },
                onKey(event) {
                    const tag = event.target && event.target.tagName;
                    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
                        return;
                    }
                    if ((event.key === 'Backspace' || event.key === 'Delete') && this.selectedId) {
                        event.preventDefault();
                        if (window.confirm('¿Quitar este cargo? Quienes le reportan pasan al nivel de arriba.')) {
                            this.$wire.deleteNode(this.selectedId);
                        }
                    }
                    if (event.key === 'Escape') {
                        this.assistant = false;
                        this.selectedId = null;
                        this.$wire.selectNode(null);
                    }
                },
                async run(method) {
                    this.busy = true;
                    try {
                        await this.$wire[method]();
                        this.fit();
                    } finally {
                        this.busy = false;
                    }
                },
                async generate() {
                    if (this.nodes.length && !window.confirm('Esto reemplaza los cargos de este organigrama. ¿Continuar?')) {
                        return;
                    }
                    this.busy = true;
                    try {
                        await this.$wire.set('prompt', this.prompt);
                        await this.$wire.ingestPrompt();
                        this.fit();
                    } finally {
                        this.busy = false;
                    }
                },
                async seed() {
                    if (this.nodes.length && !window.confirm('Esto reemplaza los cargos actuales con la estructura de las sucursales. ¿Continuar?')) {
                        return;
                    }
                    await this.run('seedFromBranches');
                },
            }));
        }
    </script>
    @endscript
</x-filament-panels::page>
