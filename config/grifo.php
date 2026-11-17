<?php
return [
'crud'=>[
'compras'=>['titulo'=>'Compras de combustible','singular'=>'compra','tabla'=>'compras_combustible','roles'=>['ADMINISTRADOR','GERENCIA','SUPERVISOR','CONTABILIDAD'],'icono'=>'compras','descripcion'=>'Registra y consulta las compras de combustible.','campos'=>[
['columnas'=>['fecha_compra','fecha'],'etiqueta'=>'Fecha de compra','tipo'=>'date','required'=>true,'lista'=>true],
['columnas'=>['producto_id'],'etiqueta'=>'Producto','tipo'=>'fk','required'=>true,'lista'=>true,'opciones'=>['tabla'=>'productos','texto'=>['nombre','codigo']]],
['columnas'=>['galones'],'etiqueta'=>'Galones','tipo'=>'number','step'=>'0.001','required'=>true,'lista'=>true],
['columnas'=>['costo_total','monto_total'],'etiqueta'=>'Costo total (S/)','tipo'=>'number','step'=>'0.01','lista'=>true],
['columnas'=>['medio_pago_id'],'etiqueta'=>'Medio de pago','tipo'=>'fk','lista'=>true,'opciones'=>['tabla'=>'medios_pago','texto'=>['nombre','codigo']]],
['columnas'=>['nombre_proveedor','proveedor_nombre','proveedor'],'etiqueta'=>'Proveedor','tipo'=>'text'],
['columnas'=>['tasa_pnp','pnp','monto_pnp'],'etiqueta'=>'PNP / tasa','tipo'=>'number','step'=>'0.01'],
['columnas'=>['observaciones'],'etiqueta'=>'Observaciones','tipo'=>'textarea']]],
'gastos'=>['titulo'=>'Gastos','singular'=>'gasto','tabla'=>'gastos','roles'=>['ADMINISTRADOR','GERENCIA','SUPERVISOR','OPERADOR','CONTABILIDAD'],'icono'=>'gastos','descripcion'=>'Control de egresos operativos y administrativos.','campos'=>[
['columnas'=>['fecha_gasto','fecha'],'etiqueta'=>'Fecha','tipo'=>'date','required'=>true,'lista'=>true],
['columnas'=>['turno_operativo_id'],'etiqueta'=>'Turno','tipo'=>'turno','lista'=>true],
['columnas'=>['categoria_id'],'etiqueta'=>'Categoría','tipo'=>'fk','lista'=>true,'opciones'=>['tabla'=>'categorias_gasto','texto'=>['nombre']]],
['columnas'=>['detalle'],'etiqueta'=>'Detalle','tipo'=>'text','required'=>true,'lista'=>true],
['columnas'=>['monto','importe'],'etiqueta'=>'Monto (S/)','tipo'=>'number','step'=>'0.01','required'=>true,'lista'=>true],
['columnas'=>['medio_pago_id'],'etiqueta'=>'Medio de pago','tipo'=>'fk','lista'=>true,'opciones'=>['tabla'=>'medios_pago','texto'=>['nombre','codigo']]],
['columnas'=>['trabajador_id'],'etiqueta'=>'Trabajador','tipo'=>'fk','opciones'=>['tabla'=>'trabajadores','texto'=>['nombre_completo']]],
['columnas'=>['observaciones'],'etiqueta'=>'Observaciones','tipo'=>'textarea']]],
'clientes'=>['titulo'=>'Clientes','singular'=>'cliente','tabla'=>'clientes','roles'=>['ADMINISTRADOR','GERENCIA','SUPERVISOR'],'icono'=>'clientes','descripcion'=>'Clientes, entidades y límites de crédito.','campos'=>[
['columnas'=>['nombre'],'etiqueta'=>'Nombre / razón social','tipo'=>'text','required'=>true,'lista'=>true],
['columnas'=>['numero_documento'],'etiqueta'=>'DNI / RUC','tipo'=>'text','lista'=>true],
['columnas'=>['tipo_cliente'],'etiqueta'=>'Tipo','tipo'=>'enum','lista'=>true],
['columnas'=>['credito_habilitado'],'etiqueta'=>'Crédito habilitado','tipo'=>'bool','lista'=>true],
['columnas'=>['limite_credito'],'etiqueta'=>'Límite de crédito (S/)','tipo'=>'number','step'=>'0.01'],
['columnas'=>['activo'],'etiqueta'=>'Cliente activo','tipo'=>'bool','lista'=>true],
['columnas'=>['observaciones'],'etiqueta'=>'Observaciones','tipo'=>'textarea']]],
'descuentos'=>['titulo'=>'Descuentos','singular'=>'descuento','tabla'=>'descuentos','roles'=>['ADMINISTRADOR','GERENCIA','SUPERVISOR','CONTABILIDAD'],'icono'=>'precios','descripcion'=>'Descuentos aplicados por producto y turno.','campos'=>[
['columnas'=>['fecha_descuento','fecha'],'etiqueta'=>'Fecha','tipo'=>'date','required'=>true,'lista'=>true],
['columnas'=>['turno_operativo_id'],'etiqueta'=>'Turno','tipo'=>'turno','lista'=>true],
['columnas'=>['producto_id'],'etiqueta'=>'Producto','tipo'=>'fk','required'=>true,'lista'=>true,'opciones'=>['tabla'=>'productos','texto'=>['nombre','codigo']]],
['columnas'=>['galones'],'etiqueta'=>'Galones','tipo'=>'number','step'=>'0.001','required'=>true,'lista'=>true],
['columnas'=>['precio_unitario_descuento','precio_descuento'],'etiqueta'=>'Precio con descuento','tipo'=>'number','step'=>'0.0001'],
['columnas'=>['monto_efectivo'],'etiqueta'=>'Monto efectivo (S/)','tipo'=>'number','step'=>'0.01','lista'=>true],
['columnas'=>['motivo'],'etiqueta'=>'Motivo','tipo'=>'textarea']]],
'caja'=>['titulo'=>'Control de caja','singular'=>'control de caja','tabla'=>'controles_diarios_caja','roles'=>['ADMINISTRADOR','GERENCIA','SUPERVISOR','CONTABILIDAD'],'icono'=>'dinero','descripcion'=>'Conteo diario de efectivo y dinero digital.','campos'=>[
['columnas'=>['fecha_control','fecha'],'etiqueta'=>'Fecha','tipo'=>'date','required'=>true,'lista'=>true],
['columnas'=>['ingreso'],'etiqueta'=>'Ingreso (S/)','tipo'=>'number','step'=>'0.01'],
['columnas'=>['efectivo_libro','efectivo'],'etiqueta'=>'Efectivo en libros','tipo'=>'number','step'=>'0.01','lista'=>true],
['columnas'=>['efectivo_contado','conteo'],'etiqueta'=>'Efectivo contado','tipo'=>'number','step'=>'0.01','lista'=>true],
['columnas'=>['diferencia'],'etiqueta'=>'Diferencia','tipo'=>'number','step'=>'0.01','lista'=>true],
['columnas'=>['digital_diario','plin_diario'],'etiqueta'=>'Digital diario','tipo'=>'number','step'=>'0.01','lista'=>true],
['columnas'=>['observaciones'],'etiqueta'=>'Observaciones','tipo'=>'textarea']]],
'ajustes-personal'=>['titulo'=>'Ajustes mensuales del personal','singular'=>'ajuste','tabla'=>'ajustes_mensuales_trabajador','roles'=>['ADMINISTRADOR','GERENCIA','SUPERVISOR','CONTABILIDAD'],'icono'=>'trabajadores','descripcion'=>'Diferencias, adelantos y liquidación mensual por trabajador.','campos'=>[
['columnas'=>['trabajador_id'],'etiqueta'=>'Trabajador','tipo'=>'fk','required'=>true,'lista'=>true,'opciones'=>['tabla'=>'trabajadores','texto'=>['nombre_completo']]],
['columnas'=>['mes_inicio','mes'],'etiqueta'=>'Mes','tipo'=>'date','required'=>true,'lista'=>true],
['columnas'=>['diferencia','variacion'],'etiqueta'=>'Diferencia acumulada (S/)','tipo'=>'number','step'=>'0.01','lista'=>true],
['columnas'=>['adelantos'],'etiqueta'=>'Adelantos (S/)','tipo'=>'number','step'=>'0.01','lista'=>true],
['columnas'=>['sueldo_base','salario_base'],'etiqueta'=>'Sueldo base (S/)','tipo'=>'number','step'=>'0.01'],
['columnas'=>['monto_neto','neto'],'etiqueta'=>'Monto neto (S/)','tipo'=>'number','step'=>'0.01','lista'=>true]]],
'pagos-turno'=>['titulo'=>'Pagos por turno','singular'=>'pago','tabla'=>'pagos_turno','roles'=>['ADMINISTRADOR','GERENCIA','SUPERVISOR','CONTABILIDAD'],'icono'=>'dinero','descripcion'=>'Desglose de pagos registrados para cada turno operativo.','campos'=>[
['columnas'=>['turno_operativo_id'],'etiqueta'=>'Turno','tipo'=>'turno','required'=>true,'lista'=>true],
['columnas'=>['medio_pago_id'],'etiqueta'=>'Medio de pago','tipo'=>'fk','required'=>true,'lista'=>true,'opciones'=>['tabla'=>'medios_pago','texto'=>['nombre','codigo']]],
['columnas'=>['monto','importe'],'etiqueta'=>'Monto (S/)','tipo'=>'number','step'=>'0.01','required'=>true,'lista'=>true],
['columnas'=>['referencia'],'etiqueta'=>'Referencia','tipo'=>'text','lista'=>true]]],
'campanas'=>['titulo'=>'Campañas y promociones','singular'=>'campaña','tabla'=>'campanas','roles'=>['ADMINISTRADOR','GERENCIA','SUPERVISOR'],'icono'=>'reportes','descripcion'=>'Planificación de promociones y acciones comerciales.','campos'=>[
['columnas'=>['nombre'],'etiqueta'=>'Nombre','tipo'=>'text','required'=>true,'lista'=>true],
['columnas'=>['tipo_campana'],'etiqueta'=>'Tipo','tipo'=>'enum','lista'=>true],
['columnas'=>['objetivo'],'etiqueta'=>'Objetivo','tipo'=>'textarea'],
['columnas'=>['fecha_inicio'],'etiqueta'=>'Inicio','tipo'=>'date','required'=>true,'lista'=>true],
['columnas'=>['fecha_fin'],'etiqueta'=>'Fin','tipo'=>'date','lista'=>true],
['columnas'=>['presupuesto'],'etiqueta'=>'Presupuesto','tipo'=>'number','step'=>'0.01'],
['columnas'=>['estado'],'etiqueta'=>'Estado','tipo'=>'enum','lista'=>true],
['columnas'=>['observaciones'],'etiqueta'=>'Observaciones','tipo'=>'textarea']]],
],
'catalogos'=>[
'productos'=>['titulo'=>'Productos','singular'=>'producto','tabla'=>'productos','campos'=>[
['columnas'=>['codigo'],'etiqueta'=>'Código','tipo'=>'text','required'=>true,'lista'=>true],['columnas'=>['nombre'],'etiqueta'=>'Nombre','tipo'=>'text','required'=>true,'lista'=>true],['columnas'=>['categoria'],'etiqueta'=>'Categoría','tipo'=>'enum','lista'=>true],['columnas'=>['unidad'],'etiqueta'=>'Unidad','tipo'=>'enum','lista'=>true],['columnas'=>['activo'],'etiqueta'=>'Activo','tipo'=>'bool','lista'=>true],['columnas'=>['fecha_descontinuacion'],'etiqueta'=>'Descontinuación','tipo'=>'date']]],
'medios-pago'=>['titulo'=>'Medios de pago','singular'=>'medio de pago','tabla'=>'medios_pago','campos'=>[
['columnas'=>['codigo'],'etiqueta'=>'Código','tipo'=>'text','required'=>true,'lista'=>true],['columnas'=>['nombre'],'etiqueta'=>'Nombre','tipo'=>'text','required'=>true,'lista'=>true],['columnas'=>['tipo','clase'],'etiqueta'=>'Tipo','tipo'=>'enum','lista'=>true],['columnas'=>['activo'],'etiqueta'=>'Activo','tipo'=>'bool','lista'=>true]]],
'categorias-gasto'=>['titulo'=>'Categorías de gasto','singular'=>'categoría','tabla'=>'categorias_gasto','campos'=>[['columnas'=>['nombre'],'etiqueta'=>'Nombre','tipo'=>'text','required'=>true,'lista'=>true]]],
'surtidores'=>['titulo'=>'Surtidores','singular'=>'surtidor','tabla'=>'surtidores','campos'=>[['columnas'=>['codigo'],'etiqueta'=>'Código','tipo'=>'text','required'=>true,'lista'=>true],['columnas'=>['nombre'],'etiqueta'=>'Nombre','tipo'=>'text','required'=>true,'lista'=>true],['columnas'=>['activo'],'etiqueta'=>'Activo','tipo'=>'bool','lista'=>true]]],
'mangueras'=>['titulo'=>'Mangueras','singular'=>'manguera','tabla'=>'mangueras','campos'=>[
['columnas'=>['surtidor_id'],'etiqueta'=>'Surtidor','tipo'=>'fk','required'=>true,'lista'=>true,'opciones'=>['tabla'=>'surtidores','texto'=>['nombre','codigo']]],['columnas'=>['producto_id'],'etiqueta'=>'Producto','tipo'=>'fk','required'=>true,'lista'=>true,'opciones'=>['tabla'=>'productos','texto'=>['nombre','codigo']]],['columnas'=>['codigo'],'etiqueta'=>'Código','tipo'=>'text','required'=>true,'lista'=>true],['columnas'=>['etiqueta_lado','lado'],'etiqueta'=>'Lado','tipo'=>'text'],['columnas'=>['activo'],'etiqueta'=>'Activo','tipo'=>'bool','lista'=>true]]],
'tanques'=>['titulo'=>'Tanques','singular'=>'tanque','tabla'=>'tanques','campos'=>[
['columnas'=>['producto_id'],'etiqueta'=>'Producto','tipo'=>'fk','required'=>true,'lista'=>true,'opciones'=>['tabla'=>'productos','texto'=>['nombre','codigo']]],['columnas'=>['codigo'],'etiqueta'=>'Código','tipo'=>'text','required'=>true,'lista'=>true],['columnas'=>['nombre'],'etiqueta'=>'Nombre','tipo'=>'text','required'=>true,'lista'=>true],['columnas'=>['capacidad_galones','capacidad_gal'],'etiqueta'=>'Capacidad (gal)','tipo'=>'number','step'=>'0.001','required'=>true,'lista'=>true],['columnas'=>['galones_no_utilizables','galones_inutilizables'],'etiqueta'=>'No despachable','tipo'=>'number','step'=>'0.001'],['columnas'=>['activo'],'etiqueta'=>'Activo','tipo'=>'bool','lista'=>true]]],
'tipos-turno'=>['titulo'=>'Tipos de turno','singular'=>'tipo de turno','tabla'=>'tipos_turno','campos'=>[
['columnas'=>['codigo'],'etiqueta'=>'Código','tipo'=>'text','required'=>true,'lista'=>true],['columnas'=>['nombre'],'etiqueta'=>'Nombre','tipo'=>'text','required'=>true,'lista'=>true],['columnas'=>['hora_inicio'],'etiqueta'=>'Hora inicio','tipo'=>'time','lista'=>true],['columnas'=>['hora_fin'],'etiqueta'=>'Hora fin','tipo'=>'time','lista'=>true],['columnas'=>['es_programado'],'etiqueta'=>'Programado','tipo'=>'bool','lista'=>true]]],
'motivos-turno'=>['titulo'=>'Motivos de turno','singular'=>'motivo','tabla'=>'motivos_turno','campos'=>[
['columnas'=>['codigo'],'etiqueta'=>'Código','tipo'=>'text','required'=>true,'lista'=>true],['columnas'=>['nombre'],'etiqueta'=>'Nombre','tipo'=>'text','required'=>true,'lista'=>true],['columnas'=>['descripcion'],'etiqueta'=>'Descripción','tipo'=>'textarea'],['columnas'=>['activo'],'etiqueta'=>'Activo','tipo'=>'bool','lista'=>true]]],
]
];
