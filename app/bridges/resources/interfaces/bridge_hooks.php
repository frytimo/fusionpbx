<?php

interface bridge_hooks extends bridge_list_page_hook, bridge_edit_hook {
	// This interface combines both list page hooks and edit page hooks for bridges
	// Implementing this interface allows a class to hook into both types of pages
}