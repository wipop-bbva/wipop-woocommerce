
is_github_repo = 'true'
wordpress_svn_credentials_id = 'jenkins-wordpress-svn-wipop'
agent= 'op_jenkins_mx_dev_slave_2023_php'
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
