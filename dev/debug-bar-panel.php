<?php
class somaDebugBarPanel extends Debug_Bar_Panel {
	function init() {
		$this->title( "Debug Console" );
	}

	function is_visible() {
		return true;
	}

	function render() {
		global $soma_dump;
		if (empty($soma_dump)) {
			echo "<h3>Nothing to report...</h3>";
			return;
		}
		if (is_array( $soma_dump ) ) {
			foreach( $soma_dump as $line )
				echo $line;
		}
	}
}

