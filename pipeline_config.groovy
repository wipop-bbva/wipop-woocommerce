
// ============================================
// CONFIGURACIÓN BÁSICA DEL PLUGIN
// ============================================

// Slug del plugin en WordPress.org (debe coincidir con el aprobado)
wordpress_plugin_slug = 'wipop'

// Archivo principal del plugin (opcional, se auto-detecta)
// wordpress_plugin_main_file = 'wipop/wipop.php'

// ============================================
// CREDENCIALES DE WORDPRESS.ORG
// ============================================

// ID de la credencial de Jenkins para acceso SVN de WordPress.org
// Debe ser tipo "Username with password"
wordpress_svn_credentials_id = 'jenkins-wordpress-svn-wipop'
git_credentials_id = 'jenkins-github-wipop-bbva'

// ============================================
// GENERACIÓN DE ZIP
// ============================================
wordpress_build_zip = true

// Archivos/directorios adicionales a excluir del ZIP
// (Los patrones comunes ya están incluidos por defecto)
wordpress_zip_excludes = [
  'internal-docs',
  'custom-config.php',
  'development-tools'
]

// ============================================
// EXCLUSIONES PARA DEPLOY A SVN
// ============================================

// Archivos/directorios a excluir al publicar en WordPress.org
// (Los patrones comunes ya están incluidos por defecto)
wordpress_svn_excludes = [
  '.git',
  '.github',
  'node_modules',
  'tests',
  'phpunit.xml',
  'composer.lock',
  '.env',
  'docker-compose.yml'
]

// ============================================
// CONFIGURACIÓN DE BUILD CON DOCKER
// ============================================

// Usar Docker para el build (si tienes docker-compose.yml)
use_docker = false


// Agente de Jenkins a usar
agent = 'op_jenkins_mx_dev_slave_2023_php'
init_agent = 'op_jenkins_mx_dev_slave_2023_php'



jte {
    pipeline_template = "wordpress_plugin"
}

libraries {
    wordpress
}

application_environments {
    dev {
        secret_name = 'op-jenkins-secrets'
        sign_apk = 'false'
    }
    sandbox {
        secret_name = 'op-jenkins-secrets'
        sign_apk = 'false'
    }
    prod {
        secret_name = 'op-jenkins-secrets'
        sign_apk = 'false'
    }
}
