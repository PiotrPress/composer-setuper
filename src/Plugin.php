<?php declare( strict_types = 1 );

namespace PiotrPress\Composer\Setuper;

use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Composer;
use Composer\Script\Event;
use Composer\IO\IOInterface;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class Plugin implements PluginInterface, EventSubscriberInterface {
    static protected array $actions = [];

    protected string $event = 'setup';
    protected int $action = 0;
    protected int $offset = 0;

    protected string $dir = '';
    protected array $vars = [];

    protected ?IOInterface $io = null;
    protected ?Filesystem $fs = null;

    public function activate( Composer $composer, IOInterface $io ) {
        $vendor = $composer->getConfig()->get( 'vendor-dir' );
        if ( \file_exists( $autoload = $vendor . '/autoload.php' ) )
            require_once $autoload;

        $this->dir = \dirname( $vendor );
        $this->io = $io;
        $this->fs = new Filesystem();

        $extra = $composer->getPackage()->getExtra();
        if ( isset( $extra[ 'setup' ] ) )
            $this->prepare( $extra[ 'setup' ] );
    }

    public function deactivate( Composer $composer, IOInterface $io ) {}
    public function uninstall( Composer $composer, IOInterface $io ) {}

    public static function getSubscribedEvents() {
        $events = [];
        foreach ( self::$actions as $event => $priorities )
            foreach ( \array_keys( $priorities ) as $priority )
                $events[ $event ][] = [ 'run', $priority ];

        return $events;
    }

    public function run( Event $event ) {
        if ( $this->event !== $event->getName() ) {
            $this->event = $event->getName();
            $this->action = 0;
            $this->offset = 0;
        }

        $priorities = self::$actions[ $event->getName() ];
        \krsort( $priorities );

        $priority = \array_slice( $priorities, $this->offset++, 1 );
        $actions = \reset( $priority );

        if ( ! $actions ) return;
        foreach ( $actions as $args ) {
            $this->io->write( sprintf(
                '[<info>%s</info>/<info>%s</info>] Event: <info>%s</info> Action: <info>%s</info>',
                ++$this->action,
                \array_sum( \array_map( 'count', self::$actions[ $event->getName() ] ) ),
                $event->getName(),
                $args[ 'action' ]
            ) );

            $this->{$args[ 'action' ]}( $this->parse( $args ) );
        }
    }

    protected function prepare( array $setup ) {
        $setup = \is_callable( $setup )
            ? \call_user_func( $setup )
            : $setup;

        foreach ( $setup as $key => $args ) {
            $args = $this->parse( (array)( \is_callable( $args )
                ? \call_user_func( $args, $key, $setup, $this->vars )
                : $args ) );

            $args[ 'event' ] = $args[ 'event' ] ?? 'setup';
            $args[ 'priority' ] = $args[ 'priority' ] ?? 0;

            $this->validate( $args );

            self::$actions[ $args[ 'event' ] ][ $args[ 'priority' ] ][] = $args;
        }
    }

    protected function parse( array $args ) {
        return $this->array_traverse( $args, function ( $value, $key ) use ( $args ) {
            if ( ! $this->is_stringable( $value ) )
                return $value;

            if ( \strpos( $value, '::' ) and \is_callable( $value ) and 'validator' !== $key )
                return \call_user_func( \substr( $value, 1 ), $key, $args, $this->vars );

            if ( '$' === ( $value[ 0 ] ?? '' ) and ( $var = $this->vars[ \substr( $value, 1 ) ] ?? false ) )
                return $var;

            return \preg_replace_callback(
                '@\{\$([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)\}@i',
                function ( $matches ) {
                    $var = $this->vars[ $matches[ 1 ] ] ?? null;
                    return ( \is_scalar( $var ) or $this->is_stringable( $var ) ) ? $var : $matches[ 0 ];
                },
                $value
            );
        } );
    }

    protected function validate( $args ) {
        $args = \json_encode( $args, JSON_THROW_ON_ERROR );
        $args = \json_decode( $args, false, 512, JSON_THROW_ON_ERROR );

        $schema = \file_get_contents( \dirname( __DIR__ ) . '/res/schema.json' );
        $schema = \json_decode( $schema, false, 512, JSON_THROW_ON_ERROR );

        $validator = new Validator();
        $validator->validate( $args, $schema, Constraint::CHECK_MODE_EXCEPTIONS );
    }

    protected function is_stringable( $var ) {
        return \is_string( $var ) or ( \is_object( $var ) and \method_exists( $var, '__toString' ) );
    }

    protected function array_traverse( array $array, callable $callback ) {
        $result = [];
        foreach ( $array as $key => $value )
            if ( \is_array( $value ) )
                $result[ $key ] = $this->array_traverse( $value, $callback );
            else
                $result[ $key ] = $callback( $value, $key );

        return $result;
    }

    protected function array_combine( array $keys, array $values ) {
        if ( 1 === \count( $values ) )
            for ( $count = 1; $count < \count( $keys ); $count++ )
                $values[ $count ] = $values[ 0 ];

        return \array_combine( $keys, $values );
    }

    protected function write( array $args ) {
        $this->io->write( $args[ 'message' ], true, \constant( IOInterface::class . '::' . \strtoupper( $args[ 'verbose' ] ?? 'normal' ) ) );
    }

    protected function set( array $args ) {
        $this->vars[ $args[ 'variable' ] ] = $args[ 'value' ];
    }

    public static function required( $value = null ) {
        if ( null === $value ) throw new \RuntimeException( 'Value is required' );
        return $value;
    }

    protected function insert( array $args ) {
        $args[ 'message' ] = '<question>' . $args[ 'message' ] . '</question> ';
        $args[ 'default' ] = $args[ 'default' ] ?? null;
        $args[ 'required' ] = $args[ 'required' ] ?? false;
        $args[ 'validator' ] = isset( $args[ 'validator' ] ) ? (array)$args[ 'validator' ] : [];

        if ( $args[ 'required' ] )
            \array_unshift( $args[ 'validator' ], self::class . '::required' );

        foreach ( $args[ 'validator' ] as $validator )
            if ( ! \is_callable( $validator ) )
                throw new \RuntimeException( "Call to undefined function {$validator}()" );

        $this->vars[ $args[ 'variable' ] ] = ( ! empty( $args[ 'validator' ] ) )
            ? $this->io->askAndValidate( $args[ 'message' ], function ( $value ) use ( $args ) {
                    foreach ( $args[ 'validator' ] as $validator )
                        $value = $validator( $value );
                    return $value;
                }, null, $args[ 'default' ] )
            : $this->io->ask( $args[ 'message' ], $args[ 'default' ] );
    }

    protected function secret( array $args ) {
        $this->vars[ $args[ 'variable' ] ] = $this->io->askAndHideAnswer(
            '<question>' . $args[ 'message' ] . '</question> '
        );
    }

    protected function select( array $args ) {
        $select = $this->io->select(
            '<question>' . $args[ 'message' ] . '</question> ',
            $args[ 'choices' ],
            $args[ 'default' ] ?? null,
            null,
            $args[ 'error' ] ?? 'Value "%s" is invalid',
            $args[ 'multiple' ] ?? false
        );

        if ( \is_array( $select ) )
            foreach ( $select as $choice )
                $this->vars[ $args[ 'variable' ] ][] = $args[ 'choices' ][ $choice ];
        else
            $this->vars[ $args[ 'variable' ] ] = $args[ 'choices' ][ $select ];
    }

    protected function confirm( array $args ) {
        $this->vars[ $args[ 'variable' ] ] = $this->io->askConfirmation(
            '<question>' . $args[ 'message' ] . '</question> ',
            $args[ 'default' ] ?? true
        );
    }

    protected function directory( array $args ) {
        $this->fs->mkdir( (array)$args[ 'path' ] );
    }

    protected function symlink( array $args ) {
        foreach ( $this->array_combine( (array)$args[ 'source' ], (array)$args[ 'target' ] ) as $source => $target )
            $this->fs->symlink( $source, $target );
    }

    protected function rename( array $args ) {
        foreach ( $this->array_combine( (array)$args[ 'source' ], (array)$args[ 'target' ] ) as $source => $target )
            $this->fs->rename( $source, $target );
    }

    protected function copy( array $args ) {
        foreach ( $this->array_combine( (array)$args[ 'source' ], (array)$args[ 'target' ] ) as $source => $target )
            if ( \is_dir( $source ) ) $this->fs->mirror( $source, $target );
            else $this->fs->copy( $source, $target );
    }

    protected function move( array $args ) {
        $this->copy( $args );
        $this->remove( [ 'path' => $args[ 'source' ] ] );
    }

    protected function remove( array $args ) {
        $this->fs->remove( (array)$args[ 'path' ] );
    }

    protected function owner( array $args ) {
        foreach ( $this->array_combine( (array)$args[ 'path' ], (array)$args[ 'owner' ] ) as $path => $owner )
            $this->fs->chown( $path, $owner, true );
    }

    protected function group( array $args ) {
        foreach ( $this->array_combine( (array)$args[ 'path' ], (array)$args[ 'group' ] ) as $path => $group )
            $this->fs->chgrp( $path, $group, true );
    }

    protected function mode( array $args ) {
        foreach ( $this->array_combine( (array)$args[ 'path' ], (array)$args[ 'mode' ] ) as $path => $mode )
            $this->fs->chmod( $path, $mode, 0000, true );
    }

    protected function dump( array $args ) {
        foreach ( $this->array_combine( (array)$args[ 'file' ], (array)( $args[ 'content' ] ?? '' ) ) as $file => $content )
            $this->fs->dumpFile( $file, $content );
    }

    protected function append( array $args ) {
        foreach ( $this->array_combine( (array)$args[ 'file' ], (array)( $args[ 'content' ] ?? '' ) ) as $file => $content )
            $this->fs->appendToFile( $file, $content );
    }

    protected function replace( array $args ) {
        foreach ( (array)$args[ 'file' ] as $file ) {
            if ( \strpbrk( $file, '\*?[' ) ) {
                $finder = new Finder();
                $pattern = \basename( $file );
                $directory = \dirname( $file );

                if ( \strpos( $directory, '**' ) !== false ) {
                    $directory = \str_replace( '**', '', $directory );
                    $finder->files()->in( $directory )->name( $pattern );
                } else $finder->files()->in( $directory )->name( $pattern )->depth( '== 0' );

                $files = \iterator_to_array( $finder );
                $this->replace( [
                    'pattern' => $args[ 'pattern' ],
                    'replace' => $args[ 'replace' ],
                    'file' => \array_map( fn( $file ) => $file->getRealPath(), $files )
                ] );
            } elseif ( ! \is_file( $file ) ) continue; else {
                $content = \file_get_contents( $file );
                $content = \preg_replace( $args[ 'pattern' ], $args[ 'replace' ], $content );
                $this->fs->dumpFile( $file, $content );
            }
        }
    }
}