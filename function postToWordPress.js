/**
 * Publica eventos aprobados a WordPress (MEC)
 */
function postToWordPress() {
  Logger.log('🚀 INICIANDO PUBLICACIÓN DE EVENTOS APROBADOS');
  
  var CONFIG = {
    STATUS_COL: 11,     // K - Estado
    EVENT_ID_COL: 16,   // Q - ID_Evento (columna 16)
    EVENT_LINK_COL: 17, // R - Enlace (columna 17)
    NOTAS_COL: 19       // S - Notas (columna 19)
  };
  
  try {
    var ui = SpreadsheetApp.getUi();
    var sheet = SpreadsheetApp.getActiveSheet();
    var data = sheet.getDataRange().getValues();
    
    // Verificar cuántos eventos aprobados hay
    var eventosAprobados = 0;
    for (var i = 1; i < data.length; i++) {
      var estado = (data[i][10] || '').toString().trim(); // Columna K
      var idEvento = (data[i][16] || '').toString();       // Columna Q
      
      if (estado === 'Aprobado' && (!idEvento || idEvento === '')) {
        eventosAprobados++;
      }
    }
    
    if (eventosAprobados === 0) {
      ui.alert('Info', 'No hay eventos aprobados pendientes de publicación.', ui.ButtonSet.OK);
      Logger.log('ℹ️ No hay eventos aprobados para publicar');
      return;
    }
    
    // Confirmar con el usuario
    var confirmacion = ui.alert(
      'Confirmar Publicación',
      '📋 Se publicarán ' + eventosAprobados + ' evento(s) aprobado(s).\n\n' +
      '¿Deseas continuar?',
      ui.ButtonSet.YES_NO
    );
    
    if (confirmacion !== ui.Button.YES) {
      Logger.log('❌ Publicación cancelada por el usuario');
      return;
    }
    
    Logger.log('📊 Eventos a publicar: ' + eventosAprobados);
    
    var publicados = 0;
    var errores = 0;
    var reporte = [];
    
    // Procesar cada fila
    for (var i = 1; i < data.length; i++) {
      var filaNumero = i + 1;
      var row = data[i];
      
      var titulo = (row[2] || '').toString().trim();       // Columna C
      var estado = (row[10] || '').toString().trim();      // Columna K
      var categoria = (row[7] || '').toString();           // Columna H
      var yaPublicado = (row[16] || '').toString();        // Columna Q
      
      Logger.log('\n--- FILA ' + filaNumero + ' ---');
      
      // Validar que sea candidato para publicación
      if (!titulo || estado !== 'Aprobado' || yaPublicado) {
        continue;
      }
      
      Logger.log('✅ PROCESANDO: ' + titulo);
      
      // Preparar datos para WordPress
      var wpData = {
        title: titulo,
        date: formatDate(row[3]),      // Columna D
        time: formatTime(row[4]),      // Columna E
        location: (row[5] || '').toString(),     // Columna F - LUGAR
        municipio: (row[6] || '').toString(),    // Columna G - MUNICIPIO
        category: categoria,
        image: (row[8] || '').toString(),        // Columna I
        description: (row[9] || '').toString(),  // Columna J
        price: (row[13] || 'gratis').toString(), // Columna N
        organizer_name: (row[14] || '').toString(), // Columna O - ORGANIZADOR
        age_limit: (row[15] || 'Todas las edades').toString(), // Columna P
        source: (row[1] || 'Formulario').toString()  // Columna B
      };
      
      // LOGS DE VERIFICACIÓN
      Logger.log('📤 DATOS A ENVIAR:');
      Logger.log('- lugar (columna F): ' + wpData.location);
      Logger.log('- municipio (columna G): ' + wpData.municipio);
      Logger.log('- organizador (columna O): ' + wpData.organizer_name);
      Logger.log('- edad (columna P): ' + wpData.age_limit);
      
      // Crear evento en WordPress
      var result = createAdvancedEventPost(wpData);
      
      // Manejar resultado
      if (result && result.eventId) {
        // ÉXITO
        sheet.getRange(filaNumero, CONFIG.STATUS_COL).setValue('Publicado');
        sheet.getRange(filaNumero, CONFIG.EVENT_ID_COL).setValue(result.eventId);
        sheet.getRange(filaNumero, CONFIG.EVENT_LINK_COL).setValue(result.link || '');
        sheet.getRange(filaNumero, CONFIG.NOTAS_COL).setValue('Publicado: ' + new Date().toLocaleString());
        
        publicados++;
        
        reporte.push({
          fila: filaNumero,
          titulo: titulo,
          id: result.eventId,
          link: result.link,
          estado: '✅ Publicado'
        });
        
        Logger.log('🎉 PUBLICADO - ID: ' + result.eventId + ' - Link: ' + result.link);
        
      } else {
        // ERROR
        var errorMsg = result && result.error ? result.error : 'Error desconocido';
        sheet.getRange(filaNumero, CONFIG.STATUS_COL).setValue('Error');
        sheet.getRange(filaNumero, CONFIG.NOTAS_COL).setValue('Error publicación: ' + errorMsg);
        
        errores++;
        reporte.push({
          fila: filaNumero,
          titulo: titulo,
          id: '',
          link: '',
          estado: '❌ Error: ' + errorMsg
        });
        
        Logger.log('❌ ERROR: ' + errorMsg);
      }
      
      // Pausa para no saturar
      Utilities.sleep(2500);
    }
    
    // Mostrar reporte final
    var mensajeFinal = '📈 RESUMEN DE PUBLICACIÓN:\n\n' +
                      '📋 Total procesados: ' + (publicados + errores) + '\n' +
                      '✅ Publicados exitosamente: ' + publicados + '\n' +
                      '❌ Errores: ' + errores + '\n\n';
    
    if (reporte.length > 0) {
      mensajeFinal += '📋 DETALLE:\n';
      reporte.forEach(function(item) {
        mensajeFinal += '• Fila ' + item.fila + ': ' + item.titulo + '\n';
        mensajeFinal += '  ' + item.estado + '\n';
        if (item.id) mensajeFinal += '  ID: ' + item.id + '\n';
      });
    }
    
    ui.alert('Publicación Completada', mensajeFinal, ui.ButtonSet.OK);
    Logger.log(mensajeFinal);
    
  } catch (e) {
    Logger.log('💥 ERROR CRÍTICO: ' + e.toString());
    SpreadsheetApp.getUi().alert('Error', 'Ocurrió un error crítico: ' + e.toString(), SpreadsheetApp.getUi().ButtonSet.OK);
  }
}
