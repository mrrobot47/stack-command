<?php

/**
 * Executes stack command on a site.
 *
 * Restarts and reloads nginx, php, mysql according to the flags passed.
 *
 * ## EXAMPLES
 *
 *     # Restart all the containers.
 *     $ ee stack restart --all
 *
 *     # Restart all the containers of example.com.
 *     $ ee stack restart example.com --all
 *
 * @package ee-cli
 */

use EE\Utils;

class Stack_Command extends EE_Command {

	private $db;
	private $logger;

	public function __construct() {
		$this->db     = EE::db();
		$this->logger = EE::get_file_logger()->withName( 'stack_command' );
	}

	/**
	 * Reload the given stacks.
	 *
	 * ## OPTIONS
	 *
	 * [<site-name>]
	 * : Name of the website for which you want to reload the stack.
	 *
	 * [--nginx]
	 * : To reload nginx.
	 *
	 * [--php]
	 * : To reload php.
	 *
	 * [--all]
	 * : To reload all the reloadable stacks.
	 */
	public function reload( $args, $assoc_args ) {

		$this->exec_stacks( $args, $assoc_args, 'reload' );

		\EE\Utils\delem_log( 'stack reload end' );
	}

	/**
	 * Restart the given stacks.
	 *
	 * ## OPTIONS
	 *
	 * [<site-name>]
	 * : Name of the website for which you want to restart the stack.
	 *
	 * [--nginx]
	 * : To restart nginx.
	 *
	 * [--php]
	 * : To restart php.
	 *
	 * [--mysql]
	 * : To restart mysql.
	 *
	 * [--all]
	 * : To restart all the stacks.
	 */
	public function restart( $args, $assoc_args ) {

		$this->exec_stacks( $args, $assoc_args, 'restart' );

		\EE\Utils\delem_log( 'stack restart end' );
	}


	/**
	 * Function to parse the parameters and get relevant stack list.
	 *
	 * @param array  $args       Command line arguments passed, here would only be the name of the site if passed.
	 * @param array  $assoc_args Array of parameter flags passed.
	 * @param String $command    The type of command requesting the stack.
	 */
	private function exec_stacks( $args, $assoc_args, $command ) {
		\EE\Utils\delem_log( "stack $command start" );
		$this->logger->debug( 'args:', $args );
		$this->logger->debug( 'assoc_args:', empty( $assoc_args ) ? array( 'NULL' ) : $assoc_args );

		$all = \EE\Utils\get_flag_value( $assoc_args, 'all' );

		if ( ! empty( $args[0] ) ) {
			$site_name = $args[0];
			if ( $this->db::site_in_db( $site_name ) ) {
				$sites = $this->db::select( array( 'sitename', 'site_path' ), array( 'sitename' => $site_name ) );
			} else {
				EE::error( "Site $site_name does not exist." );
			}
		} else if ( $all ) {
			EE::confirm( "Are you sure you want to $command all containers?", $assoc_args );
			$sites = $this->db::select( array( 'sitename', 'site_path' ) );
		} else {
			EE::error( 'Please specify a site-name or (possibly dangerous) `--all` flag for all the sites.' );
		}

		$reload = ( 'reload' === $command ) ? true : false;
		if ( $all && ! empty( $sites ) ) {
			$assoc_args = array(
				'nginx' => true,
				'php'   => true,
				'db'    => true,
			);
		}

		foreach ( $sites as $site ) {
			EE::log( "\nExecuting for " . $site['sitename'] );
			EE::log( '-----------------------' );
			@chdir( $site['site_path'] );
			foreach ( $assoc_args as $flag => $val ) {
				$this->exec_stack_from_type( $site, $flag, $reload );
			}
			EE::log( '-----------------------' );
		}

	}

	/**
	 * Execute the stacks according to the parameters.
	 *
	 * @param array  $site           Name of the websites and path.
	 * @param String $container_name Name of the container.
	 * @param bool   $reload         Is it reload or restart.
	 */
	private function exec_stack_from_type( $site, $container_name, $reload ) {
		if ( $reload ) {
			$this->reload_stack_from_type( $site, $container_name );
		} else {
			$this->launch_stack_command( "docker-compose restart $container_name", $container_name, 'restart' );

		}
	}

	/**
	 * Execute the stacks according to the paramteres.
	 *
	 * @param array  $site           Name of the websites and path.
	 * @param String $container_name Name of the container.
	 */
	private function reload_stack_from_type( $site, $container_name ) {
		switch ( $container_name ) {
			case 'nginx':
				$this->launch_stack_command( "docker-compose exec nginx bash -c 'nginx -t && nginx -s reload'", $container_name, 'reload' );
				break;
			case 'php':
				$this->launch_stack_command( "docker-compose exec php bash -c 'kill -USR2 1'", $container_name, 'reload' );
				break;
			default:
				break;
		}
	}

	/**
	 * Execute and debug the stack commands.
	 *
	 * @param String $command        The command to execute.
	 * @param String $container_name Name of the container.
	 * @param String $type           Type of command to execute: reload/restart.
	 */
	private function launch_stack_command( $command, $container_name, $type ) {
		EE::log( $type . "ing $container_name" );
		EE::debug( 'COMMAND: ' . $command );
		EE::log( shell_exec( $command ) );
		EE::log( "Done.\n" );
	}
}
